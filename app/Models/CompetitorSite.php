<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'domain', 'position', 'name_xpath', 'price_xpath', 'price_regex'])]
class CompetitorSite extends Model
{
    use HasFactory;

    public static function normalizedDomainFromUrl(?string $url): ?string
    {
        $url = trim((string) ($url ?? ''));
        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $host = is_string($host) ? trim($host) : '';
        if ($host === '') {
            return null;
        }

        $host = strtolower($host);
        $host = preg_replace('/:\d+$/', '', $host);
        $host = preg_replace('/^www\./', '', $host);

        return self::normalizedDomain($host);
    }

    public static function normalizedDomain(string $host): ?string
    {
        $host = trim(strtolower($host));
        $host = preg_replace('/:\d+$/', '', $host);
        $host = preg_replace('/^www\./', '', $host);

        if ($host === '') {
            return null;
        }

        $labels = array_values(array_filter(explode('.', $host), fn ($p) => $p !== ''));
        if (count($labels) <= 2) {
            return implode('.', $labels);
        }

        $tail2 = implode('.', array_slice($labels, -2));
        $vnSecondLevels = [
            'com.vn',
            'net.vn',
            'org.vn',
            'edu.vn',
            'gov.vn',
        ];

        if (in_array($tail2, $vnSecondLevels, true)) {
            return implode('.', array_slice($labels, -3));
        }

        return implode('.', array_slice($labels, -2));
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
