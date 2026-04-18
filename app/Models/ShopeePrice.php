<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shopee_item_id',
    'price',
    'scraped_at',
    'raw_text',
])]
class ShopeePrice extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'scraped_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ShopeeItem::class, 'shopee_item_id');
    }
}
