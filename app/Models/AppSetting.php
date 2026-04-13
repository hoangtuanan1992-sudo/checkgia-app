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
        ];
    }

    public static function current(): ?self
    {
        return static::query()->orderByDesc('id')->first();
    }
}
