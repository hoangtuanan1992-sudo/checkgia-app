<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'shopee_product_id',
    'shopee_shop_id',
    'url',
    'is_enabled',
    'price_adjustment',
    'last_price',
    'last_scraped_at',
])]
class ShopeeCompetitor extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'price_adjustment' => 'integer',
            'last_price' => 'integer',
            'last_scraped_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopeeProduct::class, 'shopee_product_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(ShopeeShop::class, 'shopee_shop_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ShopeeCompetitorPrice::class);
    }
}
