<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'compare_match_run_id',
    'product_id',
    'competitor_site_id',
    'status',
    'matched_url',
    'message',
    'processed_at',
])]
class CompareMatchRunItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(CompareMatchRun::class, 'compare_match_run_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function competitorSite(): BelongsTo
    {
        return $this->belongsTo(CompetitorSite::class);
    }
}
