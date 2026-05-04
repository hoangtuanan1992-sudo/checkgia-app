<?php

namespace App\Jobs;

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
use Illuminate\Support\Facades\Schema;

class ScrapeProductPrices implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $productId) {}

    public function handle(): void
    {
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

        $scraper = new PriceScraper;
        $notifier = new AlertNotifier;
        $ownScrapeSucceeded = false;

        try {
            $html = $scraper->fetchHtml($product->product_url);

            $nameXpaths = array_merge(
                [(string) $settings->own_name_xpath],
                UserScrapeXpath::query()
                    ->where('user_id', $product->user_id)
                    ->where('type', 'name')
                    ->orderBy('position')
                    ->pluck('xpath')
                    ->all()
            );
            $priceXpaths = array_merge(
                [(string) $settings->own_price_xpath],
                UserScrapeXpath::query()
                    ->where('user_id', $product->user_id)
                    ->where('type', 'price')
                    ->orderBy('position')
                    ->pluck('xpath')
                    ->all()
            );

            $name = $scraper->extractFirstByXPaths($html, $nameXpaths) ?? $scraper->extractTitle($html);
            $priceRaw = $scraper->extractFirstByXPaths($html, $priceXpaths);
            $price = $scraper->parsePriceToInt($priceRaw, $settings->price_regex);
            $isContactPrice = $scraper->isContactPriceText($priceRaw);

            if ($name && (! is_null($price) || $isContactPrice)) {
                $price = is_null($price) ? 0 : (int) $price;
                $updates = [
                    'name' => $name,
                    'price' => $price,
                ];
                if (Schema::hasColumn('products', 'own_scrape_failed_since')) {
                    $updates['own_scrape_failed_since'] = null;
                }
                $product->update($updates);
                $ownScrapeSucceeded = true;

                $latestOwn = ProductPriceHistory::query()->where('product_id', $product->id)->latest('fetched_at')->first();
                if ($price > 0 && (! $latestOwn || (int) $latestOwn->price !== (int) $price)) {
                    ProductPriceHistory::create([
                        'product_id' => $product->id,
                        'price' => $price,
                        'fetched_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
        }

        if (! $ownScrapeSucceeded && $this->markOwnScrapeFailureAndShouldStop($product, $settings)) {
            return;
        }

        foreach ($product->competitors as $competitor) {
            $site = $competitor->competitorSite;
            if (! $site || ! $site->price_xpath || ! $competitor->url) {
                continue;
            }

            try {
                $cHtml = $scraper->fetchHtml($competitor->url);
                $fallbacks = $site->scrapeXpaths
                    ->where('type', 'price')
                    ->sortBy('position')
                    ->pluck('xpath')
                    ->all();
                $raw = $scraper->extractFirstByXPaths($cHtml, array_merge([(string) $site->price_xpath], $fallbacks));
                $price = $scraper->parsePriceToInt($raw, $site->price_regex);

                if (! is_null($price)) {
                    $competitor->markPriceAvailable();
                    $latest = $competitor->prices()->latest('fetched_at')->first();
                    if (! $latest || (int) $latest->price !== (int) $price) {
                        $previousPrice = $latest ? (int) $latest->price : null;
                        CompetitorPrice::create([
                            'competitor_id' => $competitor->id,
                            'price' => $price,
                            'fetched_at' => now(),
                        ]);
                        $notifier->notifyOnCompetitorPriceChange($product, $competitor, (int) $price, $previousPrice);
                    }
                } else {
                    $competitor->markPriceMissing();
                }
            } catch (\Throwable $e) {
                $competitor->markPriceMissing();
            }
        }

        $product->last_scraped_at = now();
        $product->save();
    }

    private function markOwnScrapeFailureAndShouldStop(Product $product, UserScrapeSetting $settings): bool
    {
        if (! Schema::hasColumn('products', 'own_scrape_failed_since')) {
            return false;
        }

        $now = now();
        if (! $product->own_scrape_failed_since) {
            $product->own_scrape_failed_since = $now;
            $product->save();

            return false;
        }

        $enabled = Schema::hasColumn('user_scrape_settings', 'auto_delete_failed_products_enabled')
            && (bool) $settings->auto_delete_failed_products_enabled;
        if (! $enabled) {
            return false;
        }

        $days = Schema::hasColumn('user_scrape_settings', 'auto_delete_failed_products_days')
            ? max(1, (int) ($settings->auto_delete_failed_products_days ?: 7))
            : 7;

        if ($product->own_scrape_failed_since->lte($now->copy()->subDays($days))) {
            $product->delete();

            return true;
        }

        return false;
    }
}
