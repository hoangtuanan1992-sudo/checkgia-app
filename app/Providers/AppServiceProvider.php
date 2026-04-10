<?php

namespace App\Providers;

use App\Models\AppSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (Schema::hasTable('app_settings')) {
            $settings = AppSetting::current();
            if ($settings) {
                if ($settings->mail_mailer) {
                    Config::set('mail.default', $settings->mail_mailer);
                }
                if ($settings->mail_host) {
                    Config::set('mail.mailers.smtp.host', $settings->mail_host);
                }
                if ($settings->mail_port) {
                    Config::set('mail.mailers.smtp.port', (int) $settings->mail_port);
                }
                if ($settings->mail_username) {
                    Config::set('mail.mailers.smtp.username', $settings->mail_username);
                }
                if ($settings->mail_password) {
                    Config::set('mail.mailers.smtp.password', $settings->mail_password);
                }
                if (! is_null($settings->mail_encryption)) {
                    Config::set('mail.mailers.smtp.encryption', $settings->mail_encryption ?: null);
                }
                if ($settings->mail_from_address) {
                    Config::set('mail.from.address', $settings->mail_from_address);
                }
                if ($settings->mail_from_name) {
                    Config::set('mail.from.name', $settings->mail_from_name);
                }
            }
        }

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(10)->by($request->ip().'|'.mb_strtolower($email));
        });
    }
}
