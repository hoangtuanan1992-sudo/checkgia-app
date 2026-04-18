<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shopee_product_id',
    'price',
    'scraped_at',
    'raw_text',
])]
class ShopeeProductPrice extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'scraped_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopeeProduct::class, 'shopee_product_id');
    }
}
