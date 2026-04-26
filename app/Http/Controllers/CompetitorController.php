<?php

namespace App\Http\Controllers;

use App\Models\Competitor;
use App\Models\CompetitorPrice;
use App\Models\CompetitorSite;
use App\Models\CompetitorSiteTemplate;
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

        $userId = $request->user()->effectiveUserId();
        $domain = CompetitorSite::normalizedDomainFromUrl($data['url'] ?? null);
        if ($domain) {
            $site = CompetitorSite::query()
                ->where('user_id', $userId)
                ->where(function ($q) use ($domain) {
                    $q->where('domain', $domain)->orWhere('name', $domain);
                })
                ->first();
            if (! $site) {
                $site = CompetitorSite::create([
                    'user_id' => $userId,
                    'name' => $data['name'],
                    'domain' => $domain,
                ]);
            } elseif (! $site->domain) {
                $site->domain = $domain;
                $site->save();
            }
            if (! $site->name) {
                $site->name = $data['name'];
                $site->save();
            }
            $template = CompetitorSiteTemplate::query()->where('domain', $domain)->where('is_approved', true)->first();
            if ($template) {
                $template->applyToCompetitorSite($site);
            }
        } else {
            $site = CompetitorSite::query()->firstOrCreate(
                ['user_id' => $userId, 'name' => $data['name']],
                ['name' => $data['name']]
            );
        }

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

        $userId = $request->user()->effectiveUserId();
        $domain = CompetitorSite::normalizedDomainFromUrl($data['url'] ?? null);
        if ($domain) {
            $site = CompetitorSite::query()
                ->where('user_id', $userId)
                ->where(function ($q) use ($domain) {
                    $q->where('domain', $domain)->orWhere('name', $domain);
                })
                ->first();
            if (! $site) {
                $site = CompetitorSite::create([
                    'user_id' => $userId,
                    'name' => $data['name'],
                    'domain' => $domain,
                ]);
            } elseif (! $site->domain) {
                $site->domain = $domain;
                $site->save();
            }
            if (! $site->name) {
                $site->name = $data['name'];
                $site->save();
            }
            $template = CompetitorSiteTemplate::query()->where('domain', $domain)->where('is_approved', true)->first();
            if ($template) {
                $template->applyToCompetitorSite($site);
            }
        } else {
            $site = CompetitorSite::query()->firstOrCreate(
                ['user_id' => $userId, 'name' => $data['name']],
                ['name' => $data['name']]
            );
        }

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
        if ($site && ! $site->domain) {
            $domain = CompetitorSite::normalizedDomainFromUrl($url);
            if ($domain) {
                $site->domain = $domain;
                $site->save();
                $template = CompetitorSiteTemplate::query()->where('domain', $domain)->where('is_approved', true)->first();
                if ($template) {
                    $template->applyToCompetitorSite($site);
                }
            }
        }
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
        if ($request->isMethod('get')) {
            return redirect()->route('dashboard');
        }

        $user = $request->user();
        abort_unless($user, 403);
        if (! $user->isAdmin() && ((int) $product->user_id !== (int) $user->effectiveUserId() || (int) $competitorSite->user_id !== (int) $user->effectiveUserId())) {
            abort(404);
        }

        $data = $request->validate([
            'clear' => ['nullable', 'boolean'],
            'url' => ['nullable', 'url', 'max:2048'],
        ]);

        $clear = (bool) ($data['clear'] ?? false);
        $url = trim((string) ($data['url'] ?? ''));

        if (! $competitorSite->domain) {
            $domain = CompetitorSite::normalizedDomainFromUrl($url);
            if ($domain) {
                $competitorSite->domain = $domain;
                $competitorSite->save();
                $template = CompetitorSiteTemplate::query()->where('domain', $domain)->where('is_approved', true)->first();
                if ($template) {
                    $template->applyToCompetitorSite($competitorSite);
                }
            }
        }

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

        $fallbacks = $competitorSite->scrapeXpaths()->where('type', 'price')->orderBy('position')->pluck('xpath')->all();

        try {
            $scraper = new PriceScraper;
            $tgdd = $scraper->scrapeTgddPriceAndName($competitor->url);
            if (is_array($tgdd) && isset($tgdd['price']) && is_int($tgdd['price'])) {
                CompetitorPrice::create([
                    'competitor_id' => $competitor->id,
                    'price' => $tgdd['price'],
                    'fetched_at' => now(),
                ]);

                return back()->with('status', 'Đã cập nhật URL');
            }
            $html = $scraper->fetchHtml($competitor->url);
            $primary = $competitorSite->price_xpath ? [(string) $competitorSite->price_xpath] : [];
            $raw = $scraper->extractFirstByXPaths($html, array_merge($primary, $fallbacks));
            $price = $scraper->parsePriceToInt($raw, $competitorSite->price_regex);

            if (is_null($price)) {
                $structured = $scraper->extractProductNameAndPriceFromStructuredData($html);
                if (is_string($structured['price_raw'] ?? null) && trim((string) $structured['price_raw']) !== '') {
                    $price = $scraper->parsePriceToInt((string) $structured['price_raw'], $competitorSite->price_regex);
                }
            }

            if (! is_null($price)) {
                CompetitorPrice::create([
                    'competitor_id' => $competitor->id,
                    'price' => $price,
                    'fetched_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
        }

        return back()->with('status', 'Đã cập nhật URL');
    }

    public function upsertUrlByUrl(Request $request, Product $product): RedirectResponse
    {
        if ($request->isMethod('get')) {
            return redirect()->route('dashboard');
        }

        $user = $request->user();
        abort_unless($user, 403);
        if (! $user->isAdmin() && (int) $product->user_id !== (int) $user->effectiveUserId()) {
            abort(404);
        }

        $data = $request->validate([
            'clear' => ['nullable', 'boolean'],
            'url' => ['nullable', 'url', 'max:2048'],
        ]);

        $clear = (bool) ($data['clear'] ?? false);
        $url = trim((string) ($data['url'] ?? ''));
        if ($clear || $url === '') {
            return back();
        }

        $userId = $user->effectiveUserId();
        $domain = CompetitorSite::normalizedDomainFromUrl($url);
        if (! $domain) {
            return back()->withErrors(['url' => 'Không nhận diện được domain từ URL này.']);
        }

        $site = CompetitorSite::query()
            ->where('user_id', $userId)
            ->where(function ($q) use ($domain) {
                $q->where('domain', $domain)->orWhere('name', $domain);
            })
            ->first();
        if ($site) {
            if (! $site->domain) {
                $site->domain = $domain;
                $site->save();
            }
        } else {
            $nextPos = ((int) CompetitorSite::query()->where('user_id', $userId)->max('position')) + 1;
            $site = CompetitorSite::create([
                'user_id' => $userId,
                'name' => $domain,
                'domain' => $domain,
                'position' => $nextPos,
            ]);
        }

        $template = CompetitorSiteTemplate::query()->where('domain', $domain)->where('is_approved', true)->first();
        if ($template) {
            $template->applyToCompetitorSite($site);
        }

        $competitor = $product->competitors()->firstOrNew([
            'competitor_site_id' => $site->id,
        ]);
        $competitor->name = $site->name ?: $domain;
        $competitor->url = $url;
        $competitor->save();

        $fallbacks = $site->scrapeXpaths()->where('type', 'price')->orderBy('position')->pluck('xpath')->all();

        try {
            $scraper = new PriceScraper;
            $tgdd = $scraper->scrapeTgddPriceAndName($competitor->url);
            if (is_array($tgdd) && isset($tgdd['price']) && is_int($tgdd['price'])) {
                CompetitorPrice::create([
                    'competitor_id' => $competitor->id,
                    'price' => $tgdd['price'],
                    'fetched_at' => now(),
                ]);

                return back()->with('status', 'Đã cập nhật URL');
            }
            $html = $scraper->fetchHtml($competitor->url);
            $primary = $site->price_xpath ? [(string) $site->price_xpath] : [];
            $raw = $scraper->extractFirstByXPaths($html, array_merge($primary, $fallbacks));
            $price = $scraper->parsePriceToInt($raw, $site->price_regex);

            if (is_null($price)) {
                $structured = $scraper->extractProductNameAndPriceFromStructuredData($html);
                if (is_string($structured['price_raw'] ?? null) && trim((string) $structured['price_raw']) !== '') {
                    $price = $scraper->parsePriceToInt((string) $structured['price_raw'], $site->price_regex);
                }
            }

            if (! is_null($price)) {
                CompetitorPrice::create([
                    'competitor_id' => $competitor->id,
                    'price' => $price,
                    'fetched_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
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

    public function updatePriceAdjustment(Request $request, Competitor $competitor)
    {
        $product = $competitor->product;
        $user = $request->user();

        if (! $product || ! $user || ($user->role !== 'admin' && (int) $product->user_id !== (int) $user->effectiveUserId())) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Không có quyền thao tác.'], 403);
            }
            abort(403);
        }

        $data = $request->validate([
            'price_adjustment' => ['nullable', 'string', 'max:64'],
        ]);

        $input = trim((string) ($data['price_adjustment'] ?? ''));
        if ($input === '') {
            $competitor->update(['price_adjustment' => 0]);

            if ($request->expectsJson()) {
                return response()->json(['ok' => true, 'price_adjustment' => 0]);
            }

            return back()->with('status', 'Đã lưu điều chỉnh giá');
        }

        $sign = 1;
        if (str_starts_with($input, '-')) {
            $sign = -1;
            $input = trim(substr($input, 1));
        } elseif (str_starts_with($input, '+')) {
            $input = trim(substr($input, 1));
        }

        $digits = preg_replace('/[^0-9]/', '', $input) ?? '';
        if ($digits === '') {
            if ($request->expectsJson()) {
                return response()->json(['errors' => ['price_adjustment' => ['Giá điều chỉnh không hợp lệ.']]], 422);
            }

            return back()->withErrors(['price_adjustment' => 'Giá điều chỉnh không hợp lệ.']);
        }

        $adjustment = $sign * (int) $digits;
        $competitor->update(['price_adjustment' => $adjustment]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'price_adjustment' => $adjustment]);
        }

        return back()->with('status', 'Đã lưu điều chỉnh giá');
    }

    public function scrapeLatestPrice(Request $request, Competitor $competitor): RedirectResponse
    {
        $product = $competitor->product;
        abort_unless($product && $product->user_id === $request->user()->effectiveUserId(), 404);

        $site = $competitor->competitorSite;
        if (! $site) {
            return back()->withErrors(['price' => 'Chưa cấu hình XPath giá cho đối thủ này.']);
        }

        try {
            $scraper = new PriceScraper;
            $tgdd = $scraper->scrapeTgddPriceAndName($competitor->url);
            if (is_array($tgdd) && isset($tgdd['price']) && is_int($tgdd['price'])) {
                $price = $tgdd['price'];
                $latest = $competitor->prices()->latest('fetched_at')->first();
                if (! $latest || (int) $latest->price !== (int) $price) {
                    CompetitorPrice::create([
                        'competitor_id' => $competitor->id,
                        'price' => $price,
                        'fetched_at' => now(),
                    ]);
                }

                return back()->with('status', 'Đã cập nhật giá: '.number_format($price, 0, ',', '.').' đ');
            }
            $html = $scraper->fetchHtml($competitor->url);
            $fallbacks = $site->scrapeXpaths()->where('type', 'price')->orderBy('position')->pluck('xpath')->all();
            $primary = $site->price_xpath ? [(string) $site->price_xpath] : [];
            $raw = $scraper->extractFirstByXPaths($html, array_merge($primary, $fallbacks));
            $price = $scraper->parsePriceToInt($raw, $site->price_regex);

            if (is_null($price)) {
                $structured = $scraper->extractProductNameAndPriceFromStructuredData($html);
                if (is_string($structured['price_raw'] ?? null) && trim((string) $structured['price_raw']) !== '') {
                    $price = $scraper->parsePriceToInt((string) $structured['price_raw'], $site->price_regex);
                }
            }

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
