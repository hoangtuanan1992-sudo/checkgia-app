<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductPriceHistory;
use App\Models\UserScrapeSetting;
use App\Models\UserScrapeXpath;
use App\Services\PriceScraper;
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
        $settings = UserScrapeSetting::query()->firstOrCreate(['user_id' => $userId]);
        if (! $settings->own_name_xpath || ! $settings->own_price_xpath) {
            return redirect()
                ->route('dashboard.competitors')
                ->with('status', 'Vui lòng cài đặt XPath lấy tên và giá của bạn trước.');
        }

        $scraper = new PriceScraper;
        $html = $scraper->fetchHtml($data['product_url']);
        $nameXpaths = array_merge(
            [(string) $settings->own_name_xpath],
            UserScrapeXpath::query()->where('user_id', $userId)->where('type', 'name')->orderBy('position')->pluck('xpath')->all()
        );
        $priceXpaths = array_merge(
            [(string) $settings->own_price_xpath],
            UserScrapeXpath::query()->where('user_id', $userId)->where('type', 'price')->orderBy('position')->pluck('xpath')->all()
        );

        $nameDebug = $scraper->extractFirstByXPathsWithDebug($html, $nameXpaths);
        $name = $nameDebug['value'] ?? null;
        if (! $name) {
            $name = $scraper->extractTitle($html);
        }
        $priceDebug = $scraper->extractFirstByXPathsWithDebug($html, $priceXpaths);
        $priceRaw = $priceDebug['value'] ?? null;
        $price = $scraper->parsePriceToInt($priceRaw, $settings->price_regex);

        if (! $name || is_null($price)) {
            $parts = [];
            if (! $name) {
                $lines = ['Tên: không trích xuất được bằng XPath.'];
                $lines[] = '- own_name_xpath: '.(string) $settings->own_name_xpath;
                $fallbacks = array_slice($nameDebug['tried'] ?? [], 1);
                foreach ($fallbacks as $idx => $xp) {
                    $lines[] = '- tên dự phòng #'.($idx + 1).': '.$xp;
                }
                $parts[] = implode("\n", $lines);
            }
            if (is_null($price)) {
                $lines = ['Giá: không trích xuất được bằng XPath.'];
                $lines[] = '- own_price_xpath: '.(string) $settings->own_price_xpath;
                $fallbacks = array_slice($priceDebug['tried'] ?? [], 1);
                foreach ($fallbacks as $idx => $xp) {
                    $lines[] = '- giá dự phòng #'.($idx + 1).': '.$xp;
                }
                if ($settings->price_regex) {
                    $lines[] = '- Regex lọc giá: '.(string) $settings->price_regex;
                }
                $parts[] = implode("\n", $lines);
            }

            return back()
                ->withInput()
                ->withErrors(['product_url' => implode("\n\n", $parts)]);
        }

        $groupId = $data['product_group_id'] ?? null;
        if ($groupId) {
            $exists = ProductGroup::query()->where('user_id', $userId)->where('id', $groupId)->exists();
            if (! $exists) {
                $groupId = null;
            }
        }

        $groupName = trim((string) ($data['product_group_name'] ?? ''));
        if (! $groupId && $groupName !== '') {
            $group = ProductGroup::firstOrCreate([
                'user_id' => $userId,
                'name' => $groupName,
            ]);
            $groupId = $group->id;
        }

        $product = Product::create([
            'user_id' => $userId,
            'product_group_id' => $groupId,
            'name' => $name,
            'price' => $price,
            'product_url' => $data['product_url'],
        ]);

        ProductPriceHistory::create([
            'product_id' => $product->id,
            'price' => $price,
            'fetched_at' => now(),
        ]);

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
        $settings = UserScrapeSetting::query()->firstOrCreate(['user_id' => $userId]);
        if (! $settings->own_name_xpath || ! $settings->own_price_xpath) {
            return redirect()
                ->route('dashboard.competitors')
                ->with('status', 'Vui lòng cài đặt XPath lấy tên và giá của bạn trước.');
        }

        $scraper = new PriceScraper;
        $html = $scraper->fetchHtml($data['product_url']);
        $nameXpaths = array_merge(
            [(string) $settings->own_name_xpath],
            UserScrapeXpath::query()->where('user_id', $userId)->where('type', 'name')->orderBy('position')->pluck('xpath')->all()
        );
        $priceXpaths = array_merge(
            [(string) $settings->own_price_xpath],
            UserScrapeXpath::query()->where('user_id', $userId)->where('type', 'price')->orderBy('position')->pluck('xpath')->all()
        );

        $nameDebug = $scraper->extractFirstByXPathsWithDebug($html, $nameXpaths);
        $name = $nameDebug['value'] ?? null;
        if (! $name) {
            $name = $scraper->extractTitle($html);
        }
        $priceDebug = $scraper->extractFirstByXPathsWithDebug($html, $priceXpaths);
        $priceRaw = $priceDebug['value'] ?? null;
        $price = $scraper->parsePriceToInt($priceRaw, $settings->price_regex);

        if (! $name || is_null($price)) {
            $parts = [];
            if (! $name) {
                $lines = ['Tên: không trích xuất được bằng XPath.'];
                $lines[] = '- own_name_xpath: '.(string) $settings->own_name_xpath;
                $fallbacks = array_slice($nameDebug['tried'] ?? [], 1);
                foreach ($fallbacks as $idx => $xp) {
                    $lines[] = '- tên dự phòng #'.($idx + 1).': '.$xp;
                }
                $parts[] = implode("\n", $lines);
            }
            if (is_null($price)) {
                $lines = ['Giá: không trích xuất được bằng XPath.'];
                $lines[] = '- own_price_xpath: '.(string) $settings->own_price_xpath;
                $fallbacks = array_slice($priceDebug['tried'] ?? [], 1);
                foreach ($fallbacks as $idx => $xp) {
                    $lines[] = '- giá dự phòng #'.($idx + 1).': '.$xp;
                }
                if ($settings->price_regex) {
                    $lines[] = '- Regex lọc giá: '.(string) $settings->price_regex;
                }
                $parts[] = implode("\n", $lines);
            }

            return back()
                ->withInput()
                ->withErrors(['product_url' => implode("\n\n", $parts)]);
        }

        $groupId = $data['product_group_id'] ?? null;
        if ($groupId) {
            $exists = ProductGroup::query()->where('user_id', $userId)->where('id', $groupId)->exists();
            if (! $exists) {
                $groupId = null;
            }
        }

        $groupName = trim((string) ($data['product_group_name'] ?? ''));
        if (! $groupId && $groupName !== '') {
            $group = ProductGroup::firstOrCreate([
                'user_id' => $userId,
                'name' => $groupName,
            ]);
            $groupId = $group->id;
        }

        $product->update([
            'product_group_id' => $groupId,
            'name' => $name,
            'price' => $price,
            'product_url' => $data['product_url'],
        ]);

        $latest = ProductPriceHistory::query()->where('product_id', $product->id)->latest('fetched_at')->first();
        if (! $latest || (int) $latest->price !== (int) $price) {
            ProductPriceHistory::create([
                'product_id' => $product->id,
                'price' => $price,
                'fetched_at' => now(),
            ]);
        }

        return redirect()->route('dashboard')->with('status', 'Đã cập nhật sản phẩm');
    }

    public function updateUrl(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->user_id === $request->user()->effectiveUserId(), 404);

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
        $settings = UserScrapeSetting::query()->firstOrCreate(['user_id' => $userId]);
        if (! $settings->own_name_xpath || ! $settings->own_price_xpath) {
            return redirect()
                ->route('dashboard.competitors')
                ->with('status', 'Vui lòng cài đặt XPath lấy tên và giá của bạn trước.');
        }

        $scraper = new PriceScraper;
        $html = $scraper->fetchHtml($url);
        $nameXpaths = array_merge(
            [(string) $settings->own_name_xpath],
            UserScrapeXpath::query()->where('user_id', $userId)->where('type', 'name')->orderBy('position')->pluck('xpath')->all()
        );
        $priceXpaths = array_merge(
            [(string) $settings->own_price_xpath],
            UserScrapeXpath::query()->where('user_id', $userId)->where('type', 'price')->orderBy('position')->pluck('xpath')->all()
        );

        $name = $scraper->extractFirstByXPaths($html, $nameXpaths) ?? $scraper->extractTitle($html);
        $priceRaw = $scraper->extractFirstByXPaths($html, $priceXpaths);
        $price = $scraper->parsePriceToInt($priceRaw, $settings->price_regex);

        if (! $name || is_null($price)) {
            return back()
                ->withInput()
                ->withErrors(['product_url' => 'Không lấy được tên/giá. Vui lòng kiểm tra lại XPath.']);
        }

        $product->update([
            'name' => $name,
            'price' => $price,
            'product_url' => $url,
        ]);

        $latest = ProductPriceHistory::query()->where('product_id', $product->id)->latest('fetched_at')->first();
        if (! $latest || (int) $latest->price !== (int) $price) {
            ProductPriceHistory::create([
                'product_id' => $product->id,
                'price' => $price,
                'fetched_at' => now(),
            ]);
        }

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
}
