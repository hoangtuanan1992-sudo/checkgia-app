<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\CompetitorPrice;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\UserScrapeSetting;
use App\Models\UserScrapeXpath;
use App\Services\AlertNotifier;
use App\Services\PriceScraper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ScrapeProductPrices implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public function __construct(public int $productId) {}

    public function handle(): void
    {
        $now = now();
        Cache::put('checkgia:scrape-due:last_job_error', null, $now->copy()->addDays(2));
        $product = null;

        try {
            $product = Product::query()
                ->with([
                    'competitors' => function ($q) {
                        $q->with([
                            'competitorSite' => function ($s) {
                                $s->with(['scrapeXpaths' => function ($x) {
                                    $x->orderBy('type')->orderBy('position');
                                }]);
                            },
                        ]);
                    },
                ])
                ->find($this->productId);

            if (! $product || ! $product->product_url) {
                return;
            }

            $settings = UserScrapeSetting::query()->where('user_id', $product->user_id)->first();
            if (! $settings || ! $settings->own_name_xpath || ! $settings->own_price_xpath) {
                return;
            }

            $appSetting = AppSetting::current();
            $concurrency = max(1, (int) ($appSetting?->website_scrape_concurrency ?? 10));
            $timeoutSeconds = max(1, (int) ($appSetting?->website_scrape_timeout_seconds ?? 7));

            $scraper = new PriceScraper(timeoutSeconds: $timeoutSeconds, connectTimeoutSeconds: $timeoutSeconds);
            $notifier = new AlertNotifier;

            $userXpaths = UserScrapeXpath::query()
                ->where('user_id', $product->user_id)
                ->orderBy('type')
                ->orderBy('position')
                ->get()
                ->groupBy('type');

            $urlsByKey = [
                'p' => (string) $product->product_url,
            ];
            foreach ($product->competitors as $competitor) {
                $site = $competitor->competitorSite;
                if (! $site || ! $site->price_xpath || ! $competitor->url) {
                    continue;
                }

                $urlsByKey['c:'.$competitor->id] = (string) $competitor->url;
            }

            $htmlByKey = [];
            try {
                $htmlByKey = $scraper->fetchHtmlPool($urlsByKey, $concurrency);
            } catch (\Throwable $e) {
                Cache::put('checkgia:scrape-due:last_job_error', mb_substr((string) $e->getMessage(), 0, 500), $now->copy()->addDays(2));
            }

            try {
                $html = $htmlByKey['p'] ?? null;
                if (is_string($html) && $html !== '') {
                    $nameXpaths = array_merge(
                        [(string) $settings->own_name_xpath],
                        $userXpaths->get('name', collect())->pluck('xpath')->all()
                    );
                    $priceXpaths = array_merge(
                        [(string) $settings->own_price_xpath],
                        $userXpaths->get('price', collect())->pluck('xpath')->all()
                    );

                    $name = $scraper->extractFirstByXPaths($html, $nameXpaths) ?? $scraper->extractTitle($html);
                    $priceRaw = $scraper->extractFirstByXPaths($html, $priceXpaths);
                    $price = $scraper->parsePriceToInt($priceRaw, $settings->price_regex);

                    if ($name && ! is_null($price)) {
                        $product->update([
                            'name' => $name,
                            'price' => $price,
                        ]);

                        $latestOwn = ProductPriceHistory::query()
                            ->where('product_id', $product->id)
                            ->latest('fetched_at')
                            ->first();

                        if (! $latestOwn || (int) $latestOwn->price !== (int) $price) {
                            ProductPriceHistory::create([
                                'product_id' => $product->id,
                                'price' => $price,
                                'fetched_at' => $now,
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Cache::put('checkgia:scrape-due:last_job_error', mb_substr((string) $e->getMessage(), 0, 500), $now->copy()->addDays(2));
            }

            foreach ($product->competitors as $competitor) {
                $site = $competitor->competitorSite;
                if (! $site || ! $site->price_xpath || ! $competitor->url) {
                    continue;
                }

                try {
                    $cHtml = $htmlByKey['c:'.$competitor->id] ?? null;
                    if (! is_string($cHtml) || $cHtml === '') {
                        continue;
                    }

                    $fallbacks = $site->scrapeXpaths
                        ->where('type', 'price')
                        ->sortBy('position')
                        ->pluck('xpath')
                        ->all();
                    $raw = $scraper->extractFirstByXPaths($cHtml, array_merge([(string) $site->price_xpath], $fallbacks));
                    $price = $scraper->parsePriceToInt($raw, $site->price_regex);

                    if (! is_null($price)) {
                        $latest = $competitor->prices()->latest('fetched_at')->first();
                        if (! $latest || (int) $latest->price !== (int) $price) {
                            $previousPrice = $latest ? (int) $latest->price : null;
                            CompetitorPrice::create([
                                'competitor_id' => $competitor->id,
                                'price' => $price,
                                'fetched_at' => $now,
                            ]);
                            $notifier->notifyOnCompetitorPriceChange($product, $competitor, (int) $price, $previousPrice);
                        }
                    }
                } catch (\Throwable $e) {
                    Cache::put('checkgia:scrape-due:last_job_error', mb_substr((string) $e->getMessage(), 0, 500), $now->copy()->addDays(2));
                }
            }
        } catch (\Throwable $e) {
            Cache::put('checkgia:scrape-due:last_job_error', mb_substr((string) $e->getMessage(), 0, 500), $now->copy()->addDays(2));
        } finally {
            if ($product) {
                $product->last_scraped_at = $now;
                $product->save();
            }

            Cache::add('checkgia:scrape-due:last_updated', 0, $now->copy()->addDays(2));
            Cache::increment('checkgia:scrape-due:last_updated');
            Cache::put('checkgia:scrape-due:last_job_finished_at', $now->toIso8601String(), $now->copy()->addDays(2));
        }
    }
}
