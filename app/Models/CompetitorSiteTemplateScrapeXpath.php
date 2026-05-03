<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['competitor_site_template_id', 'type', 'position', 'xpath'])]
class CompetitorSiteTemplateScrapeXpath extends Model
{
    use HasFactory;

    public function competitorSiteTemplate(): BelongsTo
    {
        return $this->belongsTo(CompetitorSiteTemplate::class);
    }
}
