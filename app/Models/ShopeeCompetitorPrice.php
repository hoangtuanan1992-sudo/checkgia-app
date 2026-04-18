<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shopee_competitor_id',
    'price',
    'scraped_at',
    'raw_text',
])]
class ShopeeCompetitorPrice extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'scraped_at' => 'datetime',
        ];
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(ShopeeCompetitor::class, 'shopee_competitor_id');
    }
}
