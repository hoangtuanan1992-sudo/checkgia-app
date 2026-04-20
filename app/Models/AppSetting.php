<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'mail_mailer',
    'mail_host',
    'mail_port',
    'mail_username',
    'mail_password',
    'mail_encryption',
    'mail_from_address',
    'mail_from_name',
    'demo_user_id',
    'shopee_enabled',
    'shopee_extension_token',
    'shopee_scrape_interval_seconds',
    'shopee_rest_seconds_min',
    'shopee_rest_seconds_max',
    'shopee_max_checks_per_day',
])]
class AppSetting extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'demo_user_id' => 'integer',
            'mail_port' => 'integer',
            'mail_password' => 'encrypted',
            'shopee_enabled' => 'boolean',
            'shopee_extension_token' => 'encrypted',
            'shopee_scrape_interval_seconds' => 'integer',
            'shopee_rest_seconds_min' => 'integer',
            'shopee_rest_seconds_max' => 'integer',
            'shopee_max_checks_per_day' => 'integer',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->orderByDesc('id')->first();
    }
}
