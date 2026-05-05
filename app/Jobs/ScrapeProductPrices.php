<?php

namespace App\Jobs;

use App\Models\CompetitorPrice;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\UserScrapeSetting;
use App\Services\AlertNotifier;
use App\Services\ConfiguredProductScraper;
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

        $settings = UserScrapeSetting::query()->firstOrCreate(['user_id' => $product->user_id]);
        $scraper = new ConfiguredProductScraper(new PriceScraper);
        $notifier = new AlertNotifier;
        $ownScrapeSucceeded = false;

        try {
            $own = $scraper->scrapeOwnProduct($product->product_url, (int) $product->user_id, false);
            if ($own['name'] && ! is_null($own['price'])) {
                $price = (int) $own['price'];
                $updates = [
                    'name' => $own['name'],
                    'price' => $price,
                ];
                if (Schema::hasColumn('products', 'own_scrape_failed_since')) {
                    $updates['own_scrape_failed_since'] = null;
                }
                $product->update($updates);
                $ownScrapeSucceeded = true;

                $latestOwn = ProductPriceHistory::query()->where('product_id', $product->id)->latest('fetched_at')->first();
                if ($price > 0 && (! $latestOwn || (int) $latestOwn->price !== $price)) {
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
            if (! $competitor->url) {
                continue;
            }

            try {
                $priceResult = $scraper->scrapeCompetitorPrice($competitor->url, $competitor->competitorSite);
                $price = $priceResult['price'] ?? null;

                if (! is_null($price)) {
                    $price = (int) $price;
                    $competitor->markPriceAvailable();
                    $latest = $competitor->prices()->latest('fetched_at')->first();
                    if (! $latest || (int) $latest->price !== $price) {
                        $previousPrice = $latest ? (int) $latest->price : null;
                        CompetitorPrice::create([
                            'competitor_id' => $competitor->id,
                            'price' => $price,
                            'fetched_at' => now(),
                        ]);
                        $notifier->notifyOnCompetitorPriceChange($product, $competitor, $price, $previousPrice);
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
