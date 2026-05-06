<?php

use App\Jobs\ScrapeProductPrices;
use App\Models\Product;
use App\Models\User;
use App\Models\UserScrapeSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('checkgia:scrape-due', function () {
    $now = now('Asia/Ho_Chi_Minh');
    $hasScheduleTimes = Schema::hasColumn('user_scrape_settings', 'scrape_schedule_times');
    $hasScrapePriority = Schema::hasColumn('user_scrape_settings', 'scrape_priority');
    $columns = [
        'user_id',
        'own_name_xpath',
        'own_price_xpath',
        'scrape_interval_minutes',
    ];

    if ($hasScheduleTimes) {
        $columns[] = 'scrape_schedule_times';
    }

    if ($hasScrapePriority) {
        $columns[] = 'scrape_priority';
    }

    $settings = UserScrapeSetting::query()->get($columns)->keyBy('user_id');
    $userIds = Product::query()
        ->whereNotNull('product_url')
        ->distinct()
        ->pluck('user_id')
        ->all();

    usort($userIds, function ($a, $b) use ($settings, $hasScrapePriority): int {
        $priorityA = $hasScrapePriority ? (int) ($settings->get((int) $a)->scrape_priority ?? 50) : 50;
        $priorityB = $hasScrapePriority ? (int) ($settings->get((int) $b)->scrape_priority ?? 50) : 50;

        if ($priorityA !== $priorityB) {
            return $priorityA <=> $priorityB;
        }

        return (int) $a <=> (int) $b;
    });

    foreach ($userIds as $userId) {
        $setting = $settings->get($userId) ?? new UserScrapeSetting([
            'user_id' => $userId,
            'scrape_interval_minutes' => 10,
            'scrape_schedule_times' => '',
        ]);
        $scheduledHours = $hasScheduleTimes ? $setting->scheduledHours() : [];
        if ($scheduledHours !== []) {
            if ((int) $now->minute !== 0 || ! in_array((int) $now->hour, $scheduledHours, true)) {
                continue;
            }

            $cutoff = $now->copy()->startOfHour();
        } else {
            $interval = $hasScheduleTimes ? 10 : max(5, (int) $setting->scrape_interval_minutes);
            $cutoff = $now->copy()->subMinutes($interval);
        }

        $productIds = Product::query()
            ->where('user_id', $setting->user_id)
            ->whereNotNull('product_url')
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_scraped_at')->orWhere('last_scraped_at', '<=', $cutoff);
            })
            ->orderByRaw('last_scraped_at is null desc')
            ->orderBy('last_scraped_at')
            ->orderBy('id')
            ->pluck('id');

        foreach ($productIds as $id) {
            dispatch(new ScrapeProductPrices((int) $id));
        }
    }
})->purpose('Scrape due product/competitor prices based on user schedule');

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

Schedule::command('checkgia:scrape-due')->everyMinute()->withoutOverlapping();
