<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['agent_id', 'name', 'version', 'status', 'capabilities', 'last_seen_at', 'last_heartbeat_at', 'last_lease_at', 'last_result_at'])]
class ScrapeAgent extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'last_seen_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'last_lease_at' => 'datetime',
            'last_result_at' => 'datetime',
        ];
    }
}
