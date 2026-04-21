<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'own_url',
    'own_variant_path',
    'is_enabled',
    'last_price',
    'last_scraped_at',
])]
class ShopeeProduct extends Model
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

    public function competitors(): HasMany
    {
        return $this->hasMany(ShopeeCompetitor::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ShopeeProductPrice::class);
    }
}
