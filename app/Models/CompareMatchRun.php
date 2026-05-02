<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'mode',
    'status',
    'total_products',
    'processed_products',
    'total_cells',
    'processed_cells',
    'matched_count',
    'skipped_existing_count',
    'no_candidates_count',
    'no_match_count',
    'error_count',
    'current_product_name',
    'message',
    'error_samples',
    'started_at',
    'finished_at',
])]
class CompareMatchRun extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'error_samples' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CompareMatchRunItem::class);
    }
}
