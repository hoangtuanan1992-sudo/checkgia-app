<?php

use App\Jobs\ScrapeProductPrices;
use App\Models\Product;
use App\Models\User;
use App\Models\UserScrapeSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('checkgia:scrape-due', function () {
    $now = now();

    $settings = UserScrapeSetting::query()->get([
        'user_id',
        'own_name_xpath',
        'own_price_xpath',
        'scrape_interval_minutes',
    ]);

    foreach ($settings as $setting) {
        if (! $setting->own_name_xpath || ! $setting->own_price_xpath) {
            continue;
        }

        $interval = max(5, (int) $setting->scrape_interval_minutes);
        $cutoff = $now->copy()->subMinutes($interval);

        $productIds = Product::query()
            ->where('user_id', $setting->user_id)
            ->whereNotNull('product_url')
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_scraped_at')->orWhere('last_scraped_at', '<=', $cutoff);
            })
            ->pluck('id');

        foreach ($productIds as $id) {
            dispatch(new ScrapeProductPrices((int) $id));
        }
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

Schedule::command('checkgia:scrape-due')->everyMinute()->withoutOverlapping();
