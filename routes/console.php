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
        Cache::put('checkgia:scrape-due:last_updated', 0, $now->copy()->addDays(2));
        Cache::put('checkgia:scrape-due:last_job_finished_at', null, $now->copy()->addDays(2));

        $appSetting = AppSetting::current();
        $batchSize = max(1, (int) ($appSetting?->website_scrape_batch_per_minute ?? 40));

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
