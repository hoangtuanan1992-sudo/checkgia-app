<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'position', 'name_xpath', 'price_xpath', 'price_regex'])]
class CompetitorSite extends Model
{
    use HasFactory;

    public static function normalizedNameFromUserInput(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $looksLikeUrlOrDomain = (bool) preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $value)
            || str_starts_with(mb_strtolower($value), 'www.')
            || str_contains($value, '/')
            || (bool) preg_match('/^[a-z0-9-]+(?:\.[a-z0-9-]+)+(?::\d+)?(?:[\/?#].*)?$/i', $value);

        if (! $looksLikeUrlOrDomain) {
            return $value;
        }

        $candidate = preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $value) ? $value : 'https://'.$value;
        $host = parse_url($candidate, PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            $withoutScheme = preg_replace('/^[a-z][a-z0-9+.-]*:\/\//i', '', $value) ?? $value;
            $withoutScheme = preg_replace('/^\/\//', '', $withoutScheme) ?? $withoutScheme;
            $host = preg_split('/[\/?#]/', $withoutScheme, 2)[0] ?? '';
        }

        $host = mb_strtolower(trim((string) $host));
        if (str_contains($host, '@')) {
            $parts = explode('@', $host);
            $host = (string) end($parts);
        }

        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = trim($host, " \t\n\r\0\x0B.");

        return $host !== '' ? $host : $value;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class);
    }

    public function scrapeXpaths(): HasMany
    {
        return $this->hasMany(CompetitorSiteScrapeXpath::class);
    }
}
