<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_id', 'competitor_site_id', 'name', 'url', 'price_adjustment'])]
class Competitor extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_adjustment' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function competitorSite(): BelongsTo
    {
        return $this->belongsTo(CompetitorSite::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(CompetitorPrice::class);
    }
}
