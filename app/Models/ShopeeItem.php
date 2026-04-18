<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'url',
    'is_enabled',
    'last_price',
    'last_scraped_at',
])]
class ShopeeItem extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'last_price' => 'integer',
            'last_scraped_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ShopeePrice::class);
    }
}
