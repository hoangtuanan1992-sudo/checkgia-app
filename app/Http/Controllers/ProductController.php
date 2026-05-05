<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductPriceHistory;
use App\Services\ConfiguredProductScraper;
use App\Services\PriceScraper;
use App\Support\ProductLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('dashboard');
    }

    public function create(): View
    {
        $groups = ProductGroup::query()
            ->where('user_id', auth()->user()->effectiveUserId())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('products.create', compact('groups'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_url' => ['required', 'url', 'max:2048'],
            'product_group_id' => ['nullable', 'integer'],
            'product_group_name' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = $request->user()->effectiveUserId();
        if (ProductLimit::wouldExceed($userId)) {
            return back()
                ->withInput()
                ->withErrors(['product_url' => ProductLimit::message($userId)]);
        }

        $scraped = $this->scrapeProductUrl($data['product_url'], $userId);
        if (! $scraped) {
            return back()
                ->withInput()
                ->withErrors(['product_url' => 'Không lấy được tên/giá. Hãy kiểm tra Thư viện XPath theo domain hoặc XPath riêng của shop.']);
        }

        $groupId = $this->resolveProductGroupId($userId, $data['product_group_id'] ?? null, $data['product_group_name'] ?? null);

        $product = Product::create([
            'user_id' => $userId,
            'product_group_id' => $groupId,
            'name' => $scraped['name'],
            'price' => (int) $scraped['price'],
            'product_url' => $data['product_url'],
        ]);

        $this->storePriceHistoryIfChanged($product, (int) $scraped['price']);

        return redirect()->route('dashboard')->with('status', 'Đã thêm sản phẩm');
    }

    public function edit(Request $request, Product $product): View
    {
        abort_unless($product->user_id === $request->user()->effectiveUserId(), 404);

        $groups = ProductGroup::query()
            ->where('user_id', $request->user()->effectiveUserId())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('products.edit', compact('product', 'groups'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->user_id === $request->user()->effectiveUserId(), 404);

        $data = $request->validate([
            'product_url' => ['required', 'url', 'max:2048'],
            'product_group_id' => ['nullable', 'integer'],
            'product_group_name' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = $request->user()->effectiveUserId();
        $scraped = $this->scrapeProductUrl($data['product_url'], $userId);
        if (! $scraped) {
            return back()
                ->withInput()
                ->withErrors(['product_url' => 'Không lấy được tên/giá. Hãy kiểm tra Thư viện XPath theo domain hoặc XPath riêng của shop.']);
        }

        $groupId = $this->resolveProductGroupId($userId, $data['product_group_id'] ?? null, $data['product_group_name'] ?? null);

        $product->update([
            'product_group_id' => $groupId,
            'name' => $scraped['name'],
            'price' => (int) $scraped['price'],
            'product_url' => $data['product_url'],
        ]);

        $this->storePriceHistoryIfChanged($product, (int) $scraped['price']);

        return redirect()->route('dashboard')->with('status', 'Đã cập nhật sản phẩm');
    }

    public function updateUrl(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        if (! $user->isAdmin() && (int) $product->user_id !== (int) $user->effectiveUserId()) {
            abort(404);
        }

        $data = $request->validate([
            'clear' => ['nullable', 'boolean'],
            'product_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $clear = (bool) ($data['clear'] ?? false);
        if ($clear) {
            $product->update([
                'product_url' => null,
            ]);

            return back()->with('status', 'Đã xoá link sản phẩm');
        }

        $url = trim((string) ($data['product_url'] ?? ''));
        if ($url === '') {
            return back()->withErrors(['product_url' => 'Vui lòng nhập URL hoặc bấm Xoá.']);
        }

        $userId = $request->user()->effectiveUserId();
        $scraped = $this->scrapeProductUrl($url, $userId);
        if (! $scraped) {
            return back()
                ->withInput()
                ->withErrors(['product_url' => 'Không lấy được tên/giá. Hãy kiểm tra Thư viện XPath theo domain hoặc XPath riêng của shop.']);
        }

        $product->update([
            'name' => $scraped['name'],
            'price' => (int) $scraped['price'],
            'product_url' => $url,
        ]);

        $this->storePriceHistoryIfChanged($product, (int) $scraped['price']);

        return back()->with('status', 'Đã cập nhật link sản phẩm');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->user_id === $request->user()->effectiveUserId(), 404);

        $product->delete();

        return redirect()->route('dashboard')->with('status', 'Đã xoá sản phẩm');
    }

    public function destroyFromDashboard(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        if (! $user->isAdmin() && (int) $product->user_id !== (int) $user->effectiveUserId()) {
            abort(404);
        }

        $product->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('status', 'Đã xoá sản phẩm');
    }

    public function destroyFilteredFromDashboard(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_if($user->isViewer(), 403);

        $query = Product::query()->where('user_id', $user->effectiveUserId());
        $this->applyDashboardDeleteFilters($query, $request);

        $count = (clone $query)->count();
        $deleted = $query->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'matched' => $count,
                'deleted' => $deleted,
            ]);
        }

        return back()->with('status', 'Đã xoá '.$deleted.' sản phẩm');
    }

    public function assignFilteredGroupFromDashboard(Request $request, ProductGroup $productGroup): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_if($user->isViewer(), 403);

        $userId = $user->effectiveUserId();
        abort_unless((int) $productGroup->user_id === (int) $userId, 404);

        $query = Product::query()->where('user_id', $userId);
        $this->applyDashboardDeleteFilters($query, $request);

        $count = (clone $query)->count();
        $updated = $query->update(['product_group_id' => $productGroup->id]);

        return response()->json([
            'ok' => true,
            'matched' => $count,
            'updated' => $updated,
            'group' => [
                'id' => (int) $productGroup->id,
                'name' => (string) $productGroup->name,
            ],
        ]);
    }

    private function scrapeProductUrl(string $url, int $userId): ?array
    {
        $scraped = (new ConfiguredProductScraper(new PriceScraper))
            ->scrapeOwnProduct($url, $userId, true);

        if (! $scraped['name'] || is_null($scraped['price'])) {
            return null;
        }

        return [
            'name' => $scraped['name'],
            'price' => (int) $scraped['price'],
        ];
    }

    private function resolveProductGroupId(int $userId, mixed $groupId, mixed $groupName): ?int
    {
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

    private function storePriceHistoryIfChanged(Product $product, int $price): void
    {
        if ($price <= 0) {
            return;
        }

        $latest = ProductPriceHistory::query()->where('product_id', $product->id)->latest('fetched_at')->first();
        if (! $latest || (int) $latest->price !== $price) {
            ProductPriceHistory::create([
                'product_id' => $product->id,
                'price' => $price,
                'fetched_at' => now(),
            ]);
        }
    }

    private function applyDashboardDeleteFilters($query, Request $request): void
    {
        $search = trim((string) $request->query('q', $request->input('q', '')));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%');
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        $group = (string) $request->query('group', $request->input('group', ''));
        if ($group === '__none__') {
            $query->whereNull('product_group_id');
        } elseif ($group !== '' && ctype_digit($group)) {
            $query->where('product_group_id', (int) $group);
        }
    }
}
