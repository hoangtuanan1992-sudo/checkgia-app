<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'own_name_xpath', 'own_price_xpath', 'price_regex', 'scrape_interval_minutes', 'scrape_schedule_times', 'auto_delete_failed_products_enabled', 'auto_delete_failed_products_days'])]
class UserScrapeSetting extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'auto_delete_failed_products_enabled' => 'boolean',
            'auto_delete_failed_products_days' => 'integer',
        ];
    }

    public static function normalizeScheduleTimes(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        preg_match_all('/\d{1,2}/', $value, $matches);

        $hours = collect($matches[0] ?? [])
            ->map(fn (string $hour): int => (int) $hour)
            ->filter(fn (int $hour): bool => $hour >= 0 && $hour <= 23)
            ->unique()
            ->sort()
            ->values();

        return $hours->implode(' ');
    }

    /**
     * @return list<int>
     */
    public function scheduledHours(): array
    {
        $normalized = self::normalizeScheduleTimes((string) $this->scrape_schedule_times);
        if ($normalized === '') {
            return [];
        }

        return collect(explode(' ', $normalized))
            ->map(fn (string $hour): int => (int) $hour)
            ->values()
            ->all();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
