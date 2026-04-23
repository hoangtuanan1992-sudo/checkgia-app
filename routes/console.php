<?php

declare(strict_types=1);

use App\Models\AppSetting;
use App\Models\CompetitorPrice;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\User;
use App\Models\UserScrapeSetting;
use App\Models\UserScrapeXpath;
use App\Services\AlertNotifier;
use App\Services\PriceScraper;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('checkgia:scrape-due', function () {
    $now = now();

    $lockKey = 'checkgia:scrape-due:lock';
    $locked = ! Cache::add($lockKey, 1, $now->copy()->addMinutes(2));
    if ($locked) {
        return 0;
    }

    try {
        Cache::put('checkgia:scrape-due:last_started_at', $now->toIso8601String(), $now->copy()->addDays(2));

        $appSetting = AppSetting::current();
        $batchSize = max(1, (int) ($appSetting?->website_scrape_batch_per_minute ?? 40));
        $concurrency = max(1, (int) ($appSetting?->website_scrape_concurrency ?? 10));
        $timeoutSeconds = max(1, (int) ($appSetting?->website_scrape_timeout_seconds ?? 7));

        $settings = UserScrapeSetting::query()
            ->whereNotNull('own_name_xpath')
            ->where('own_name_xpath', '!=', '')
            ->whereNotNull('own_price_xpath')
            ->where('own_price_xpath', '!=', '')
            ->get([
                'user_id',
                'own_name_xpath',
                'own_price_xpath',
                'scrape_interval_minutes',
                'price_regex',
            ]);

        $selectedIds = [];
        $remaining = $batchSize;

        foreach ($settings as $setting) {
            if ($remaining <= 0) {
                break;
            }

            $interval = max(5, (int) $setting->scrape_interval_minutes);
            $cutoff = $now->copy()->subMinutes($interval);

            $ids = Product::query()
                ->where('user_id', $setting->user_id)
                ->whereNotNull('product_url')
                ->where('product_url', '!=', '')
                ->where(function ($q) use ($cutoff) {
                    $q->whereNull('last_scraped_at')->orWhere('last_scraped_at', '<=', $cutoff);
                })
                ->orderByRaw('last_scraped_at is null desc')
                ->orderByDesc('created_at')
                ->orderBy('last_scraped_at')
                ->orderByDesc('id')
                ->limit($remaining)
                ->pluck('id')
                ->all();

            foreach ($ids as $id) {
                $selectedIds[] = (int) $id;
                $remaining--;
                if ($remaining <= 0) {
                    break;
                }
            }
        }

        $selectedIds = array_values(array_unique($selectedIds));
        if ($selectedIds === []) {
            Cache::put('checkgia:scrape-due:last_selected', 0, $now->copy()->addDays(2));
            Cache::put('checkgia:scrape-due:last_finished_at', now()->toIso8601String(), $now->copy()->addDays(2));

            return 0;
        }

        Cache::put('checkgia:scrape-due:last_selected', count($selectedIds), $now->copy()->addDays(2));

        $products = Product::query()
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
            ->whereIn('id', $selectedIds)
            ->get();

        if ($products->isEmpty()) {
            Cache::put('checkgia:scrape-due:last_finished_at', now()->toIso8601String(), $now->copy()->addDays(2));

            return 0;
        }

        $userIds = $products->pluck('user_id')->unique()->values();
        $settingsByUser = $settings->keyBy('user_id');
        $xpathsByUser = UserScrapeXpath::query()
            ->whereIn('user_id', $userIds)
            ->orderBy('type')
            ->orderBy('position')
            ->get()
            ->groupBy('user_id');

        $scraper = new PriceScraper(timeoutSeconds: $timeoutSeconds, connectTimeoutSeconds: $timeoutSeconds);
        $notifier = new AlertNotifier;

        $urlsByKey = [];
        foreach ($products as $product) {
            if (! $product->product_url) {
                continue;
            }

            $urlsByKey['p:'.$product->id] = (string) $product->product_url;

            foreach ($product->competitors as $competitor) {
                $site = $competitor->competitorSite;
                if (! $site || ! $site->price_xpath || ! $competitor->url) {
                    continue;
                }

                $urlsByKey['c:'.$competitor->id] = (string) $competitor->url;
            }
        }

        $htmlByKey = $scraper->fetchHtmlPool($urlsByKey, $concurrency);

        $updatedCount = 0;
        foreach ($products as $product) {
            try {
                if (! $product->product_url) {
                    continue;
                }

                $setting = $settingsByUser->get($product->user_id);
                if (! $setting || ! $setting->own_name_xpath || ! $setting->own_price_xpath) {
                    continue;
                }

                $userX = $xpathsByUser->get($product->user_id) ?? collect();

                $ownHtml = $htmlByKey['p:'.$product->id] ?? null;
                if (is_string($ownHtml) && $ownHtml !== '') {
                    $nameXpaths = array_merge(
                        [(string) $setting->own_name_xpath],
                        $userX->where('type', 'name')->pluck('xpath')->all()
                    );
                    $priceXpaths = array_merge(
                        [(string) $setting->own_price_xpath],
                        $userX->where('type', 'price')->pluck('xpath')->all()
                    );

                    $name = $scraper->extractFirstByXPaths($ownHtml, $nameXpaths) ?? $scraper->extractTitle($ownHtml);
                    $priceRaw = $scraper->extractFirstByXPaths($ownHtml, $priceXpaths);
                    $price = $scraper->parsePriceToInt($priceRaw, $setting->price_regex);

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

                foreach ($product->competitors as $competitor) {
                    $site = $competitor->competitorSite;
                    if (! $site || ! $site->price_xpath || ! $competitor->url) {
                        continue;
                    }

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
                }
            } catch (Throwable $e) {
            } finally {
                $product->forceFill(['last_scraped_at' => $now])->save();
                $updatedCount++;
            }
        }

        Cache::put('checkgia:scrape-due:last_updated', $updatedCount, $now->copy()->addDays(2));
        Cache::put('checkgia:scrape-due:last_finished_at', now()->toIso8601String(), $now->copy()->addDays(2));
    } finally {
        Cache::forget($lockKey);
    }
})->purpose('Scrape due product/competitor prices based on user interval');

Artisan::command('checkgia:admin-create {email} {--name=Admin} {--password=}', function () {
    $email = (string) $this->argument('email');
    $name = (string) $this->option('name');
    $password = (string) $this->option('password');

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Email không hợp lệ');

        return 1;
    }

    if ($password === '') {
        $password = bin2hex(random_bytes(6)).'A@1';
    }

    $canonical = User::canonicalEmail($email);

    $user = User::query()->updateOrCreate(
        ['email_canonical' => $canonical],
        [
            'name' => $name,
            'email' => $email,
            'role' => 'admin',
            'parent_user_id' => null,
            'password' => $password,
        ]
    );

    $this->info('Đã tạo/cập nhật admin: '.$user->email);
    $this->info('Mật khẩu: '.$password);

    return 0;
})->purpose('Create an admin account (outputs generated password if not provided)');

Schedule::command('checkgia:scrape-due')->everyMinute()->withoutOverlapping(15);
