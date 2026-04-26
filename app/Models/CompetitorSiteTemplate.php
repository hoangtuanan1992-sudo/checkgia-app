<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['domain', 'name', 'name_xpath', 'price_xpath', 'price_regex', 'is_approved', 'approved_at'])]
class CompetitorSiteTemplate extends Model
{
    use HasFactory;

    protected $casts = [
        'is_approved' => 'bool',
        'approved_at' => 'datetime',
    ];

    public function scrapeXpaths(): HasMany
    {
        return $this->hasMany(CompetitorSiteTemplateScrapeXpath::class);
    }

    public function applyToCompetitorSite(CompetitorSite $site): void
    {
        $dirty = false;

        if (! $site->name_xpath && $this->name_xpath) {
            $site->name_xpath = $this->name_xpath;
            $dirty = true;
        }
        if (! $site->price_xpath && $this->price_xpath) {
            $site->price_xpath = $this->price_xpath;
            $dirty = true;
        }
        if (! $site->price_regex && $this->price_regex) {
            $site->price_regex = $this->price_regex;
            $dirty = true;
        }

        if ($dirty) {
            $site->save();
        }

        $existing = $site->scrapeXpaths()
            ->whereIn('type', ['name', 'price'])
            ->get()
            ->groupBy('type')
            ->map(fn ($v) => $v->count());

        $templateXpaths = $this->scrapeXpaths()
            ->whereIn('type', ['name', 'price'])
            ->orderBy('type')
            ->orderBy('position')
            ->get()
            ->groupBy('type');

        foreach (['name', 'price'] as $type) {
            if (($existing->get($type) ?? 0) > 0) {
                continue;
            }

            $rows = $templateXpaths->get($type, collect())
                ->map(fn ($x) => [
                    'competitor_site_id' => $site->id,
                    'type' => $type,
                    'position' => (int) $x->position,
                    'xpath' => (string) $x->xpath,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if ($rows) {
                CompetitorSiteScrapeXpath::query()->insert($rows);
            }
        }
    }
}
