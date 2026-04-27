<?php

namespace App\Http\Controllers;

use App\Models\CompetitorPrice;
use App\Models\CompetitorSite;
use App\Models\CompetitorSiteTemplate;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductPriceHistory;
use App\Models\ShopeeProduct;
use App\Models\User;
use App\Models\UserScrapeSetting;
use App\Models\UserScrapeXpath;
use App\Services\PriceScraper;
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

        $userId = $request->user()->effectiveUserId();
        $limit = (int) (User::query()->whereKey($userId)->value('product_limit') ?? 100);
        $used = (int) Product::query()->where('user_id', $userId)->count()
            + (int) ShopeeProduct::query()->where('user_id', $userId)->count();
        if ($used >= $limit) {
            return back()
                ->withInput()
                ->with('status', 'Bạn đã đến giới hạn so sánh '.$limit.' sản phẩm, để dùng tiếp hãy xóa bớt sản phẩm so sánh hoặc liên hệ admin để nâng cấp tài khoản');
        }

        $settings = UserScrapeSetting::query()->firstOrCreate(['user_id' => $userId]);
        if (! $settings->own_name_xpath || ! $settings->own_price_xpath) {
            return redirect()
                ->route('dashboard.competitors')
                ->with('status', 'Vui lòng cài đặt XPath lấy tên và giá của bạn trước.');
        }

        $scraper = new PriceScraper;
        $nameDebug = ['tried' => []];
        $priceDebug = ['tried' => []];
        $tgdd = $scraper->scrapeTgddPriceAndName((string) $validated['product_url']);
        if (is_array($tgdd) && isset($tgdd['price']) && is_int($tgdd['price'])) {
            $price = $tgdd['price'];
            $name = isset($tgdd['name']) && is_string($tgdd['name']) && trim($tgdd['name']) !== '' ? trim($tgdd['name']) : null;
            if (! $name) {
                $html = $scraper->fetchHtml($validated['product_url']);
                $name = $scraper->extractTitle($html);
            }
        } else {
            $html = $scraper->fetchHtml($validated['product_url']);

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
                $structured = $scraper->extractProductNameAndPriceFromStructuredData($html);
                if (! $name && is_string($structured['name'] ?? null) && trim((string) $structured['name']) !== '') {
                    $name = (string) $structured['name'];
                }
                if (is_null($price) && is_string($structured['price_raw'] ?? null) && trim((string) $structured['price_raw']) !== '') {
                    $price = $scraper->parsePriceToInt((string) $structured['price_raw'], $settings->price_regex);
                }
            }

            if (is_null($price) || (int) $price <= 0) {
                $tgdd = $scraper->scrapeTgddPriceAndName((string) $validated['product_url']);
                if (is_array($tgdd) && isset($tgdd['price']) && is_int($tgdd['price'])) {
                    $price = $tgdd['price'];
                    if (! $name && isset($tgdd['name']) && is_string($tgdd['name']) && trim($tgdd['name']) !== '') {
                        $name = trim($tgdd['name']);
                    }
                }
            }
        }

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

        $groupId = $validated['product_group_id'] ?? null;
        if ($groupId) {
            $exists = ProductGroup::query()->where('user_id', $userId)->where('id', $groupId)->exists();
            if (! $exists) {
                $groupId = null;
            }
        }

        $groupName = trim((string) ($validated['product_group_name'] ?? ''));
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
            'product_url' => $validated['product_url'],
        ]);

        ProductPriceHistory::create([
            'product_id' => $product->id,
            'price' => $price,
            'fetched_at' => now(),
        ]);

        $sites = CompetitorSite::query()
            ->where('user_id', $userId)
            ->with(['scrapeXpaths' => function ($q) {
                $q->orderBy('type')->orderBy('position');
            }])
            ->get(['id', 'name', 'domain', 'position', 'price_xpath', 'price_regex'])
            ->keyBy('id');
        $sitesByDomain = $sites
            ->filter(fn ($s) => is_string($s->domain) && trim((string) $s->domain) !== '')
            ->keyBy('domain');

        $urls = $validated['competitor_urls'] ?? [];
        foreach ($urls as $key => $url) {
            $url = is_string($url) ? trim($url) : null;

            if ($url === null || $url === '') {
                continue;
            }

            $site = null;
            $keyInt = is_string($key) && preg_match('/^\d+$/', $key) ? (int) $key : (is_int($key) ? (int) $key : null);
            if (! is_null($keyInt) && $sites->has($keyInt)) {
                $site = $sites->get($keyInt);
            } else {
                $domain = CompetitorSite::normalizedDomainFromUrl($url);
                if (! $domain) {
                    continue;
                }

                $site = $sitesByDomain->get($domain);
                if (! $site) {
                    $site = $sites->first(fn ($s) => (string) $s->name === (string) $domain);
                    if (! $site) {
                        $site = CompetitorSite::query()
                            ->where('user_id', $userId)
                            ->where(function ($q) use ($domain) {
                                $q->where('domain', $domain)->orWhere('name', $domain);
                            })
                            ->first();
                    }

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

                    $site = $site->fresh(['scrapeXpaths' => function ($q) {
                        $q->orderBy('type')->orderBy('position');
                    }]);

                    $sites->put($site->id, $site);
                    $sitesByDomain->put($domain, $site);
                }
            }

            $competitor = $product->competitors()->firstOrNew([
                'competitor_site_id' => $site->id,
            ]);
            $competitor->name = $site->name;
            $competitor->url = $url;
            $competitor->save();

            try {
                $tgdd = $scraper->scrapeTgddPriceAndName($url);
                if (is_array($tgdd) && isset($tgdd['price']) && is_int($tgdd['price'])) {
                    $cPrice = $tgdd['price'];
                    $latest = $competitor->prices()->latest('fetched_at')->first();
                    if (! $latest || (int) $latest->price !== (int) $cPrice) {
                        CompetitorPrice::create([
                            'competitor_id' => $competitor->id,
                            'price' => $cPrice,
                            'fetched_at' => now(),
                        ]);
                    }

                    continue;
                }
            } catch (\Throwable $e) {
            }

            if ($site->price_xpath) {
                try {
                    $cHtml = $scraper->fetchHtml($url);
                    $fallbacks = $site->scrapeXpaths->where('type', 'price')->sortBy('position')->pluck('xpath')->all();
                    $cPriceRaw = $scraper->extractFirstByXPaths($cHtml, array_merge([(string) $site->price_xpath], $fallbacks));
                    $cPrice = $scraper->parsePriceToInt($cPriceRaw, $site->price_regex);
                    if (is_null($cPrice)) {
                        $structured = $scraper->extractProductNameAndPriceFromStructuredData($cHtml);
                        if (is_string($structured['price_raw'] ?? null) && trim((string) $structured['price_raw']) !== '') {
                            $cPrice = $scraper->parsePriceToInt((string) $structured['price_raw'], $site->price_regex);
                        }
                    }
                    if (! is_null($cPrice)) {
                        $latest = $competitor->prices()->latest('fetched_at')->first();
                        if (! $latest || (int) $latest->price !== (int) $cPrice) {
                            CompetitorPrice::create([
                                'competitor_id' => $competitor->id,
                                'price' => $cPrice,
                                'fetched_at' => now(),
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        return redirect()->route('dashboard')->with('status', 'Đã thêm sản phẩm');
    }
}
