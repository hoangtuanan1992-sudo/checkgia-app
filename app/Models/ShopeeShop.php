<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'is_own', 'position'])]
class ShopeeShop extends Model
{
    protected function casts(): array
    {
        return [
            'is_own' => 'boolean',
            'position' => 'integer',
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
}
