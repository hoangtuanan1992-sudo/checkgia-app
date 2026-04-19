<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agent_key',
    'name',
    'note',
    'version',
    'platform',
    'user_agent',
    'is_enabled',
    'is_approved',
    'pair_code',
    'api_token',
    'last_error',
    'last_task_url',
    'last_report_at',
    'mode',
    'assigned_user_id',
    'last_seen_at',
    'last_scrape_at',
])]
class ShopeeAgent extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_approved' => 'boolean',
            'api_token' => 'encrypted',
            'last_report_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_scrape_at' => 'datetime',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
