<?php

declare(strict_types=1);

use App\Jobs\ScrapeProductPrices;
use App\Models\AppSetting;
use App\Models\Product;
use App\Models\User;
use App\Models\UserScrapeSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('checkgia:scrape-due', function () {
    $now = now();
    $tz = 'Asia/Ho_Chi_Minh';
    $nowLocal = $now->copy()->setTimezone($tz);

    $lockKey = 'checkgia:scrape-due:lock';
    $locked = ! Cache::add($lockKey, 1, $now->copy()->addMinutes(2));
    if ($locked) {
        return 0;
    }

    try {
        Cache::put('checkgia:scrape-due:last_started_at', $now->toIso8601String(), $now->copy()->addDays(2));
        Cache::put('checkgia:scrape-due:last_updated', 0, $now->copy()->addDays(2));
        Cache::put('checkgia:scrape-due:last_job_finished_at', null, $now->copy()->addDays(2));

        $appSetting = AppSetting::current();
        $batchSize = max(1, (int) ($appSetting?->website_scrape_batch_per_minute ?? 40));

        $cols = [
            'user_id',
            'own_name_xpath',
            'own_price_xpath',
            'scrape_interval_minutes',
            'price_regex',
        ];
        if (Schema::hasColumn('user_scrape_settings', 'scrape_schedule_times')) {
            $cols[] = 'scrape_schedule_times';
        }

        $settings = UserScrapeSetting::query()
            ->whereNotNull('own_name_xpath')
            ->where('own_name_xpath', '!=', '')
            ->whereNotNull('own_price_xpath')
            ->where('own_price_xpath', '!=', '')
            ->get($cols);

        $selectedIds = [];
        $remaining = $batchSize;
        $hasScheduleColumn = in_array('scrape_schedule_times', $cols, true);

        $decodeScheduleMinutes = function ($raw) {
            if (! is_string($raw) || trim($raw) === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                return [];
            }
            $mins = [];
            foreach ($decoded as $v) {
                $s = trim((string) $v);
                if ($s === '') {
                    continue;
                }
                if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $s, $m) !== 1) {
                    continue;
                }
                $h = (int) $m[1];
                $mm = (int) $m[2];
                if ($h < 0 || $h > 23 || $mm < 0 || $mm > 59) {
                    continue;
                }
                $mins[] = $h * 60 + $mm;
            }
            $mins = array_values(array_unique($mins));
            sort($mins);

            return $mins;
        };

        $resolveScheduleCutoff = function (array $mins) use ($nowLocal, $tz) {
            if ($mins === []) {
                return null;
            }
            $nowMinutes = ((int) $nowLocal->format('H')) * 60 + (int) $nowLocal->format('i');
            $first = $mins[0];
            $last = $mins[count($mins) - 1];

            $slotMin = null;
            $slotDay = null;
            $nextMin = null;
            $nextDay = null;

            if ($nowMinutes >= $first) {
                $slotMin = $first;
                foreach ($mins as $m) {
                    if ($m <= $nowMinutes) {
                        $slotMin = $m;
                    } else {
                        $nextMin = $m;
                        break;
                    }
                }
                $slotDay = $nowLocal->toDateString();
                if (is_null($nextMin)) {
                    $nextMin = $first;
                    $nextDay = $nowLocal->copy()->addDay()->toDateString();
                } else {
                    $nextDay = $slotDay;
                }
            } else {
                $slotMin = $last;
                $slotDay = $nowLocal->copy()->subDay()->toDateString();
                $nextMin = $first;
                $nextDay = $nowLocal->toDateString();
            }

            $slotStr = sprintf('%02d:%02d', intdiv($slotMin, 60), $slotMin % 60);
            $nextStr = sprintf('%02d:%02d', intdiv($nextMin, 60), $nextMin % 60);

            $slotStart = \Illuminate\Support\Carbon::createFromFormat('Y-m-d H:i', $slotDay.' '.$slotStr, $tz);
            $nextStart = \Illuminate\Support\Carbon::createFromFormat('Y-m-d H:i', $nextDay.' '.$nextStr, $tz);

            if ($nowLocal->lt($slotStart) || ! $nowLocal->lt($nextStart)) {
                return null;
            }

            return $slotStart;
        };

        foreach ($settings as $setting) {
            if ($remaining <= 0) {
                break;
            }

            $scheduleMins = $hasScheduleColumn ? $decodeScheduleMinutes((string) ($setting->scrape_schedule_times ?? '')) : [];
            $scheduleCutoffLocal = $resolveScheduleCutoff($scheduleMins);
            $scheduleCutoff = $scheduleCutoffLocal ? $scheduleCutoffLocal->copy()->setTimezone($now->getTimezone()) : null;
            $interval = max(5, (int) $setting->scrape_interval_minutes);
            $cutoff = $now->copy()->subMinutes($interval);

            $ids = Product::query()
                ->where('user_id', $setting->user_id)
                ->whereNotNull('product_url')
                ->where('product_url', '!=', '')
                ->where(function ($q) use ($cutoff, $scheduleCutoff) {
                    if ($scheduleCutoff) {
                        $q->whereNull('last_scraped_at')->orWhere('last_scraped_at', '<', $scheduleCutoff);
                    } else {
                        $q->whereNull('last_scraped_at')->orWhere('last_scraped_at', '<=', $cutoff);
                    }
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
            Cache::put('checkgia:scrape-due:last_dispatched', 0, $now->copy()->addDays(2));
            Cache::put('checkgia:scrape-due:last_finished_at', now()->toIso8601String(), $now->copy()->addDays(2));

            return 0;
        }

        Cache::put('checkgia:scrape-due:last_selected', count($selectedIds), $now->copy()->addDays(2));
        $existing = Product::query()->whereIn('id', $selectedIds)->pluck('id')->all();
        $existing = array_values(array_map('intval', $existing));

        foreach ($existing as $id) {
            dispatch(new ScrapeProductPrices($id));
        }

        Cache::put('checkgia:scrape-due:last_dispatched', count($existing), $now->copy()->addDays(2));
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
