<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

#[Fillable(['product_id', 'competitor_site_id', 'name', 'url', 'price_adjustment', 'price_missing_at'])]
class Competitor extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_adjustment' => 'integer',
            'price_missing_at' => 'datetime',
        ];
    }

    public function markPriceAvailable(): void
    {
        if (! Schema::hasColumn('competitors', 'price_missing_at')) {
            return;
        }

        if ($this->price_missing_at === null) {
            return;
        }

        $this->forceFill(['price_missing_at' => null])->save();
    }

    public function markPriceMissing(): void
    {
        if (! Schema::hasColumn('competitors', 'price_missing_at')) {
            return;
        }

        $this->forceFill(['price_missing_at' => now()])->save();
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
