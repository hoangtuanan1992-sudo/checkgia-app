<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['competitor_site_id', 'type', 'position', 'xpath'])]
class CompetitorSiteScrapeXpath extends Model
{
    use HasFactory;

    public function competitorSite(): BelongsTo
    {
        return $this->belongsTo(CompetitorSite::class);
    }
}
