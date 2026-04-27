<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['user_id', 'name'])]
class CompetitorSiteGroup extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function competitorSites(): BelongsToMany
    {
        return $this->belongsToMany(CompetitorSite::class, 'competitor_site_group_sites')
            ->withTimestamps()
            ->orderBy('competitor_sites.position')
            ->orderBy('competitor_sites.name');
    }
}
