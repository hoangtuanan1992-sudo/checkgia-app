<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'email_enabled',
    'email_to',
    'telegram_enabled',
    'telegram_bot_token',
    'telegram_chat_id',
    'alert_competitor_cheaper_percent',
    'alert_competitor_drop_amount',
    'notify_all_price_changes',
    'notify_all_price_changes_title',
    'notify_all_price_changes_body',
    'alert_cheaper_title',
    'alert_cheaper_body',
    'alert_drop_title',
    'alert_drop_body',
])]
class UserNotificationSetting extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'telegram_enabled' => 'boolean',
            'telegram_bot_token' => 'encrypted',
            'alert_competitor_cheaper_percent' => 'integer',
            'alert_competitor_drop_amount' => 'integer',
            'notify_all_price_changes' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
