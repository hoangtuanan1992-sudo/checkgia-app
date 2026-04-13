<?php

namespace App\Http\Controllers;

use App\Models\Competitor;
use App\Models\CompetitorPrice;
use App\Models\CompetitorSite;
use App\Models\Product;
use App\Services\PriceScraper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompetitorController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->user_id === $request->user()->effectiveUserId(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $site = CompetitorSite::firstOrCreate(
            ['user_id' => $request->user()->effectiveUserId(), 'name' => $data['name']],
            ['name' => $data['name']]
        );

        $product->competitors()->create([
            'competitor_site_id' => $site->id,
            'name' => $data['name'],
            'url' => $data['url'],
        ]);

        return back()->with('status', 'Đã thêm đối thủ');
    }

    public function update(Request $request, Product $product, Competitor $competitor): RedirectResponse
    {
        abort_unless($product->user_id === $request->user()->effectiveUserId() && $competitor->product_id === $product->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $site = CompetitorSite::firstOrCreate(
            ['user_id' => $request->user()->effectiveUserId(), 'name' => $data['name']],
            ['name' => $data['name']]
        );

        $competitor->update([
            'competitor_site_id' => $site->id,
            'name' => $data['name'],
            'url' => $data['url'],
        ]);

        return back()->with('status', 'Đã cập nhật đối thủ');
    }

    public function destroy(Request $request, Product $product, Competitor $competitor): RedirectResponse
    {
        abort_unless($product->user_id === $request->user()->effectiveUserId() && $competitor->product_id === $product->id, 404);

        $competitor->delete();

        return back()->with('status', 'Đã xoá đối thủ');
    }

    public function updateUrl(Request $request, Competitor $competitor): RedirectResponse
    {
        $product = $competitor->product;
        abort_unless($product && $product->user_id === $request->user()->effectiveUserId(), 404);

        $data = $request->validate([
            'clear' => ['nullable', 'boolean'],
            'url' => ['nullable', 'url', 'max:2048'],
        ]);

        $clear = (bool) ($data['clear'] ?? false);
        $url = trim((string) ($data['url'] ?? ''));
        if ($clear || $url === '') {
            $competitor->delete();

            return back()->with('status', 'Đã xoá URL');
        }

        $competitor->update([
            'url' => $url,
        ]);

        $site = $competitor->competitorSite;
        if ($site && $site->price_xpath) {
            try {
                $scraper = new PriceScraper;
                $html = $scraper->fetchHtml($competitor->url);
                $fallbacks = $site->scrapeXpaths()->where('type', 'price')->orderBy('position')->pluck('xpath')->all();
                $raw = $scraper->extractFirstByXPaths($html, array_merge([(string) $site->price_xpath], $fallbacks));
                $price = $scraper->parsePriceToInt($raw, $site->price_regex);

                if (! is_null($price)) {
                    $latest = $competitor->prices()->latest('fetched_at')->first();
                    if (! $latest || (int) $latest->price !== (int) $price) {
                        CompetitorPrice::create([
                            'competitor_id' => $competitor->id,
                            'price' => $price,
                            'fetched_at' => now(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        return back()->with('status', 'Đã cập nhật URL');
    }

    public function upsertUrl(Request $request, Product $product, CompetitorSite $competitorSite): RedirectResponse
    {
        abort_unless($product->user_id === $request->user()->effectiveUserId() && $competitorSite->user_id === $request->user()->effectiveUserId(), 404);

        $data = $request->validate([
            'clear' => ['nullable', 'boolean'],
            'url' => ['nullable', 'url', 'max:2048'],
        ]);

        $clear = (bool) ($data['clear'] ?? false);
        $url = trim((string) ($data['url'] ?? ''));

        if ($clear || $url === '') {
            $existing = $product->competitors()->where('competitor_site_id', $competitorSite->id)->first();
            if ($existing) {
                $existing->delete();
            }

            return back()->with('status', 'Đã xoá URL');
        }

        $competitor = $product->competitors()->firstOrNew([
            'competitor_site_id' => $competitorSite->id,
        ]);
        $competitor->name = $competitorSite->name;
        $competitor->url = $url;
        $competitor->save();

        if ($competitorSite->price_xpath) {
            try {
                $scraper = new PriceScraper;
                $html = $scraper->fetchHtml($competitor->url);
                $fallbacks = $competitorSite->scrapeXpaths()->where('type', 'price')->orderBy('position')->pluck('xpath')->all();
                $raw = $scraper->extractFirstByXPaths($html, array_merge([(string) $competitorSite->price_xpath], $fallbacks));
                $price = $scraper->parsePriceToInt($raw, $competitorSite->price_regex);

                if (! is_null($price)) {
                    CompetitorPrice::create([
                        'competitor_id' => $competitor->id,
                        'price' => $price,
                        'fetched_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
            }
        }

        return back()->with('status', 'Đã cập nhật URL');
    }

    public function storePrice(Request $request, Competitor $competitor): RedirectResponse
    {
        $product = $competitor->product;
        abort_unless($product && $product->user_id === $request->user()->effectiveUserId(), 404);

        $data = $request->validate([
            'price' => ['required', 'integer', 'min:0'],
        ]);

        CompetitorPrice::create([
            'competitor_id' => $competitor->id,
            'price' => $data['price'],
            'fetched_at' => now(),
        ]);

        return back()->with('status', 'Đã thêm giá đối thủ');
    }

    public function updatePriceAdjustment(Request $request, Competitor $competitor): RedirectResponse
    {
        $product = $competitor->product;
        abort_unless($product && $product->user_id === $request->user()->effectiveUserId(), 404);

        $data = $request->validate([
            'price_adjustment' => ['nullable', 'string', 'max:64'],
        ]);

        $input = trim((string) ($data['price_adjustment'] ?? ''));
        if ($input === '') {
            $competitor->update(['price_adjustment' => 0]);

            return back()->with('status', 'Đã lưu điều chỉnh giá');
        }

        $sign = 1;
        if (str_starts_with($input, '-')) {
            $sign = -1;
            $input = trim(mb_substr($input, 1));
        } elseif (str_starts_with($input, '+')) {
            $input = trim(mb_substr($input, 1));
        }

        $digits = preg_replace('/[^0-9]/', '', $input) ?? '';
        if ($digits === '') {
            return back()->withErrors(['price_adjustment' => 'Giá điều chỉnh không hợp lệ.']);
        }

        $adjustment = $sign * (int) $digits;
        $competitor->update(['price_adjustment' => $adjustment]);

        return back()->with('status', 'Đã lưu điều chỉnh giá');
    }

    public function scrapeLatestPrice(Request $request, Competitor $competitor): RedirectResponse
    {
        $product = $competitor->product;
        abort_unless($product && $product->user_id === $request->user()->effectiveUserId(), 404);

        $site = $competitor->competitorSite;
        if (! $site || ! $site->price_xpath) {
            return back()->withErrors(['price' => 'Chưa cấu hình XPath giá cho đối thủ này.']);
        }

        try {
            $scraper = new PriceScraper;
            $html = $scraper->fetchHtml($competitor->url);
            $fallbacks = $site->scrapeXpaths()->where('type', 'price')->orderBy('position')->pluck('xpath')->all();
            $raw = $scraper->extractFirstByXPaths($html, array_merge([(string) $site->price_xpath], $fallbacks));
            $price = $scraper->parsePriceToInt($raw, $site->price_regex);

            if (is_null($price)) {
                return back()->withErrors(['price' => 'Không lấy được giá. Vui lòng kiểm tra lại XPath/Regex.']);
            }

            $latest = $competitor->prices()->latest('fetched_at')->first();
            if (! $latest || (int) $latest->price !== (int) $price) {
                CompetitorPrice::create([
                    'competitor_id' => $competitor->id,
                    'price' => $price,
                    'fetched_at' => now(),
                ]);
            }

            return back()->with('status', 'Đã cập nhật giá: '.number_format($price, 0, ',', '.').' đ');
        } catch (\Throwable $e) {
            return back()->withErrors(['price' => 'Không truy cập được trang đối thủ hoặc bị chặn.']);
        }
    }
}
