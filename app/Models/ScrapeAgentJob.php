<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'job_uuid',
    'target_key',
    'type',
    'product_id',
    'competitor_id',
    'competitor_site_id',
    'url',
    'domain',
    'variant_key',
    'variant_name',
    'status',
    'priority',
    'attempts',
    'max_attempts',
    'leased_by_agent_id',
    'completed_by_agent_id',
    'lease_token',
    'leased_at',
    'lease_expires_at',
    'next_run_at',
    'finished_at',
    'last_error_code',
    'last_error',
    'result_payload',
])]
class ScrapeAgentJob extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'priority' => 'integer',
            'leased_at' => 'datetime',
            'lease_expires_at' => 'datetime',
            'next_run_at' => 'datetime',
            'finished_at' => 'datetime',
            'result_payload' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }

    public function competitorSite(): BelongsTo
    {
        return $this->belongsTo(CompetitorSite::class);
    }
}
