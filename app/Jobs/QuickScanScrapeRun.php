<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\UserScrapeSetting;
use App\Models\UserScrapeXpath;
use App\Services\PriceScraper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class QuickScanScrapeRun implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 180;

    public function __construct(public int $runId) {}

    public function handle(): void
    {
        $run = DB::table('quick_scan_runs')->where('id', $this->runId)->first();
        if (! $run) {
            return;
        }

        if ((int) ($run->stop_requested ?? 0) === 1) {
            DB::table('quick_scan_runs')
                ->where('id', $this->runId)
                ->update([
                    'status' => 'paused',
                    'updated_at' => now(),
                ]);
            return;
        }

        $userId = (int) ($run->user_id ?? 0);
        if ($userId <= 0) {
            return;
        }

        $setting = UserScrapeSetting::query()->where('user_id', $userId)->first();
        if (! $setting || ! $setting->own_name_xpath || ! $setting->own_price_xpath) {
            DB::table('quick_scan_runs')
                ->where('id', $this->runId)
                ->update([
                    'status' => 'error',
                    'updated_at' => now(),
                ]);
            return;
        }

        $appSetting = null;
        try {
            $appSetting = AppSetting::current();
        } catch (\Throwable) {
        }
        $timeoutSeconds = max(1, (int) ($appSetting?->website_scrape_timeout_seconds ?? 7));
        $concurrency = max(1, (int) ($appSetting?->website_scrape_concurrency ?? 10));
        $concurrency = min(10, $concurrency);

        $nameXpaths = array_merge(
            [(string) $setting->own_name_xpath],
            UserScrapeXpath::query()->where('user_id', $userId)->where('type', 'name')->orderBy('position')->pluck('xpath')->all()
        );
        $priceXpaths = array_merge(
            [(string) $setting->own_price_xpath],
            UserScrapeXpath::query()->where('user_id', $userId)->where('type', 'price')->orderBy('position')->pluck('xpath')->all()
        );

        $items = DB::table('quick_scan_items')
            ->where('run_id', $this->runId)
            ->whereNull('fetched_at')
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'url'])
            ->all();

        if ($items === []) {
            DB::table('quick_scan_runs')
                ->where('id', $this->runId)
                ->update([
                    'status' => 'done',
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);
            return;
        }

        DB::table('quick_scan_runs')
            ->where('id', $this->runId)
            ->update([
                'status' => 'running',
                'started_at' => $run->started_at ?: now(),
                'updated_at' => now(),
            ]);

        $urlsByKey = [];
        foreach ($items as $row) {
            $id = (int) ($row->id ?? 0);
            $url = trim((string) ($row->url ?? ''));
            if ($id <= 0 || $url === '') {
                continue;
            }
            $urlsByKey[(string) $id] = $url;
        }

        if ($urlsByKey === []) {
            DB::table('quick_scan_runs')
                ->where('id', $this->runId)
                ->update([
                    'status' => 'done',
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);
            return;
        }

        $scraper = new PriceScraper(timeoutSeconds: $timeoutSeconds, connectTimeoutSeconds: $timeoutSeconds);
        try {
            $htmlByKey = $scraper->fetchHtmlPool($urlsByKey, $concurrency);
        } catch (\Throwable) {
            $htmlByKey = [];
        }

        $now = now();
        $processed = 0;
        $products = 0;
        $priced = 0;

        foreach ($urlsByKey as $id => $url) {
            $html = $htmlByKey[$id] ?? null;
            if (! is_string($html) || trim($html) === '') {
                try {
                    $html = $scraper->fetchHtml($url);
                } catch (\Throwable) {
                    $html = null;
                }
            }
            $name = null;
            $price = null;
            $isProduct = false;

            if (is_string($html) && trim($html) !== '') {
                $name = $scraper->extractFirstByXPaths($html, $nameXpaths);
                if (! $name) {
                    $name = $scraper->extractTitle($html);
                }
                $priceRaw = $scraper->extractFirstByXPaths($html, $priceXpaths);
                $price = $scraper->parsePriceToInt($priceRaw, (string) ($setting->price_regex ?? null));

                $isProduct = (is_string($name) && trim($name) !== '');
                if ($isProduct) {
                    $products++;
                }
                if (! is_null($price) && (int) $price > 0) {
                    $priced++;
                }
            }

            DB::table('quick_scan_items')
                ->where('id', (int) $id)
                ->update([
                    'name' => $name ? mb_substr(trim((string) $name), 0, 255) : null,
                    'price' => $price,
                    'fetched_at' => $now,
                    'is_product' => $isProduct ? 1 : 0,
                    'updated_at' => $now,
                ]);
            $processed++;
        }

        DB::table('quick_scan_runs')
            ->where('id', $this->runId)
            ->update([
                'processed_count' => DB::raw('processed_count + '.(int) $processed),
                'product_count' => DB::raw('product_count + '.(int) $products),
                'priced_count' => DB::raw('priced_count + '.(int) $priced),
                'updated_at' => now(),
            ]);

        $runAfter = DB::table('quick_scan_runs')->where('id', $this->runId)->first(['stop_requested']);
        if ($runAfter && (int) ($runAfter->stop_requested ?? 0) === 1) {
            DB::table('quick_scan_runs')
                ->where('id', $this->runId)
                ->update([
                    'status' => 'paused',
                    'updated_at' => now(),
                ]);
            return;
        }

        dispatch(new self($this->runId));
    }
}
