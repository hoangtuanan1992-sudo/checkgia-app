<?php

namespace App\Http\Controllers;

use App\Models\CompetitorPrice;
use App\Models\CompetitorSite;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductPriceHistory;
use App\Services\ConfiguredProductScraper;
use App\Services\PriceScraper;
use App\Support\ProductLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardProductController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_url' => ['required', 'url', 'max:2048'],
            'product_group_id' => ['nullable', 'integer'],
            'product_group_name' => ['nullable', 'string', 'max:255'],
            'competitor_urls' => ['array'],
            'competitor_urls.*' => ['nullable', 'url', 'max:2048'],
        ]);

        $authUser = $request->user();
        $userId = $authUser->effectiveUserId();
        if (ProductLimit::wouldExceed($userId)) {
            return back()
                ->withInput()
                ->withErrors(['product_url' => ProductLimit::message($userId)]);
        }

        $groupId = $this->resolveProductGroupId(
            $authUser,
            $userId,
            $validated['product_group_id'] ?? null,
            $validated['product_group_name'] ?? null
        );
        if ($groupId instanceof RedirectResponse) {
            return $groupId;
        }

        $configuredScraper = new ConfiguredProductScraper(new PriceScraper);
        $scrapedProduct = $configuredScraper->scrapeOwnProduct($validated['product_url'], $userId, true);
        $name = $scrapedProduct['name'];
        $price = $scrapedProduct['price'];

        if (! $name || is_null($price)) {
            return back()
                ->withInput()
                ->withErrors(['product_url' => 'Không lấy được tên/giá. Hãy kiểm tra Thư viện XPath theo domain hoặc XPath riêng của shop.']);
        }

        $product = Product::create([
            'user_id' => $userId,
            'product_group_id' => $groupId,
            'name' => $name,
            'price' => (int) $price,
            'product_url' => $validated['product_url'],
        ]);

        if ((int) $price > 0) {
            ProductPriceHistory::create([
                'product_id' => $product->id,
                'price' => (int) $price,
                'fetched_at' => now(),
            ]);
        }

        $sitesQuery = CompetitorSite::query()
            ->where('user_id', $userId)
            ->with(['scrapeXpaths' => function ($q) {
                $q->orderBy('type')->orderBy('position');
            }])
            ->orderBy('position')
            ->orderBy('name');
        if ($authUser->isViewer()) {
            $allowedCompetitorSiteIds = $this->visibleCompetitorSiteIds($authUser, $userId);
            if ($allowedCompetitorSiteIds === []) {
                $sitesQuery->whereRaw('1 = 0');
            } else {
                $sitesQuery->whereIn('id', $allowedCompetitorSiteIds);
            }
        }

        $sites = $sitesQuery
            ->get(['id', 'name', 'domain', 'price_xpath', 'price_regex'])
            ->keyBy('id');

        $urls = $validated['competitor_urls'] ?? [];
        foreach ($urls as $siteId => $url) {
            $siteId = (int) $siteId;
            $url = is_string($url) ? trim($url) : null;

            if ($url === null || $url === '') {
                continue;
            }

            $site = $sites->get($siteId);
            if (! $site) {
                continue;
            }

            $competitor = $product->competitors()->create([
                'competitor_site_id' => $site->id,
                'name' => $site->name,
                'url' => $url,
            ]);

            try {
                $priceResult = $configuredScraper->scrapeCompetitorPrice($url, $site);
            } catch (\Throwable $e) {
                $priceResult = ['price' => null];
            }

            if (! is_null($priceResult['price'] ?? null)) {
                $competitor->markPriceAvailable();
                $latest = $competitor->prices()->latest('fetched_at')->first();
                if (! $latest || (int) $latest->price !== (int) $priceResult['price']) {
                    CompetitorPrice::create([
                        'competitor_id' => $competitor->id,
                        'price' => (int) $priceResult['price'],
                        'fetched_at' => now(),
                    ]);
                }
            } else {
                $competitor->markPriceMissing();
            }
        }

        return redirect()->route('dashboard')->with('status', 'Đã thêm sản phẩm');
    }

    private function resolveProductGroupId($authUser, int $userId, mixed $groupId, mixed $groupName): int|RedirectResponse|null
    {
        if ($authUser->isViewer()) {
            if (empty($groupId)) {
                return back()
                    ->withInput()
                    ->withErrors(['product_group_id' => 'Tài khoản con phải chọn nhóm sản phẩm được cấp quyền trước khi thêm sản phẩm.']);
            }

            $allowedProductGroupIds = $authUser->visibleProductGroupIds();
            if ($allowedProductGroupIds === []) {
                return back()
                    ->withInput()
                    ->withErrors(['product_group_id' => 'Tài khoản con chưa được cấp nhóm sản phẩm để thêm sản phẩm.']);
            }

            $resolved = ProductGroup::query()
                ->where('user_id', $userId)
                ->whereIn('id', $allowedProductGroupIds)
                ->whereKey((int) $groupId)
                ->value('id');

            if (! $resolved) {
                return back()
                    ->withInput()
                    ->withErrors(['product_group_id' => 'Nhóm sản phẩm không hợp lệ hoặc bạn không được cấp quyền.']);
            }

            return (int) $resolved;
        }

        $resolvedGroupId = $groupId ? (int) $groupId : null;
        if ($resolvedGroupId) {
            $exists = ProductGroup::query()->where('user_id', $userId)->where('id', $resolvedGroupId)->exists();
            if (! $exists) {
                $resolvedGroupId = null;
            }
        }

        $name = trim((string) ($groupName ?? ''));
        if (! $resolvedGroupId && $name !== '') {
            $group = ProductGroup::firstOrCreate([
                'user_id' => $userId,
                'name' => $name,
            ]);
            $resolvedGroupId = (int) $group->id;
        }

        return $resolvedGroupId;
    }

    /**
     * @return array<int, int>
     */
    private function visibleCompetitorSiteIds($authUser, int $userId): array
    {
        $groupIds = $authUser->visibleCompetitorSiteGroupIds();
        if ($groupIds === []) {
            return [];
        }

        return CompetitorSite::query()
            ->where('user_id', $userId)
            ->whereHas('groups', fn ($q) => $q->whereIn('competitor_site_groups.id', $groupIds))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
