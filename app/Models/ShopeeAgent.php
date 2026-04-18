<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agent_key',
    'name',
    'version',
    'platform',
    'user_agent',
    'is_enabled',
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
            'last_seen_at' => 'datetime',
            'last_scrape_at' => 'datetime',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
