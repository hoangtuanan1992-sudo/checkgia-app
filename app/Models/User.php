<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

#[Fillable(['name', 'email', 'password', 'role', 'parent_user_id', 'visible_product_group_ids', 'visible_competitor_site_group_ids', 'service_start_date', 'service_end_date', 'admin_note', 'product_limit', 'allow_compare_match'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'service_start_date' => 'date',
            'service_end_date' => 'date',
            'visible_product_group_ids' => 'array',
            'visible_competitor_site_group_ids' => 'array',
            'allow_compare_match' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $user) {
            if ($user->email) {
                $user->email_canonical = self::canonicalEmail((string) $user->email);
            }
        });
    }

    public static function canonicalEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));

        $local = $email;
        $domain = '';
        if (str_contains($email, '@')) {
            [$local, $domain] = explode('@', $email, 2);
        }

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = preg_replace('/\+.*/', '', $local) ?? $local;
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $domain ? $local.'@'.$domain : $email;
    }

    public function parentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function subUsers(): HasMany
    {
        return $this->hasMany(User::class, 'parent_user_id');
    }

    public function effectiveUserId(): int
    {
        if ($this->isAdmin()) {
            $impersonateId = session('impersonate_user_id');
            if ($impersonateId) {
                $u = static::query()->find((int) $impersonateId);
                if ($u) {
                    return (int) ($u->parent_user_id ?: $u->id);
                }
            }
        }

        return (int) ($this->parent_user_id ?: $this->id);
    }

    public static function hasProductLimitColumn(): bool
    {
        try {
            return Schema::hasColumn('users', 'product_limit');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function resolveProductLimitById(int $userId): int
    {
        if (! static::hasProductLimitColumn()) {
            return 100;
        }

        $limit = (int) (static::query()->whereKey($userId)->value('product_limit') ?? 100);

        return $limit > 0 ? $limit : 100;
    }

    public static function hasCompareMatchColumn(): bool
    {
        try {
            return Schema::hasColumn('users', 'allow_compare_match');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function compareMatchEnabledForId(int $userId): bool
    {
        if (! static::hasCompareMatchColumn()) {
            return false;
        }

        return (bool) static::query()->whereKey($userId)->value('allow_compare_match');
    }

    public static function hasSubUserVisibilityColumns(): bool
    {
        try {
            return Schema::hasColumn('users', 'visible_product_group_ids')
                && Schema::hasColumn('users', 'visible_competitor_site_group_ids');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, int>
     */
    public function visibleProductGroupIds(): array
    {
        if (! self::hasSubUserVisibilityColumns()) {
            return [];
        }

        return self::normalizeStoredIds($this->visible_product_group_ids ?? null);
    }

    /**
     * @return array<int, int>
     */
    public function visibleCompetitorSiteGroupIds(): array
    {
        if (! self::hasSubUserVisibilityColumns()) {
            return [];
        }

        return self::normalizeStoredIds($this->visible_competitor_site_group_ids ?? null);
    }

    /**
     * @return array<int, int>
     */
    private static function normalizeStoredIds(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function shopeeProducts(): HasMany
    {
        return $this->hasMany(ShopeeProduct::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function serviceRemainingText(): string
    {
        if (! $this->service_start_date || ! $this->service_end_date) {
            return '';
        }

        $tz = 'Asia/Ho_Chi_Minh';
        $today = Carbon::now($tz)->toDateString();
        $now = Carbon::createFromFormat('Y-m-d', $today, $tz);
        $start = Carbon::createFromFormat('Y-m-d', $this->service_start_date->format('Y-m-d'), $tz);
        $end = Carbon::createFromFormat('Y-m-d', $this->service_end_date->format('Y-m-d'), $tz);

        if ($now->lt($start)) {
            $days = (int) $now->diffInDays($start);

            return 'Chưa bắt đầu (còn '.$days.' ngày)';
        }

        if ($now->gt($end)) {
            return 'Hết hạn';
        }

        $endInclusive = $end->copy()->addDay();
        $months = (int) $now->diffInMonths($endInclusive);
        $afterMonths = $now->copy()->addMonths($months);
        $days = (int) $afterMonths->diffInDays($endInclusive);

        if ($months > 0 && $days > 0) {
            return 'Còn '.$months.' tháng '.$days.' ngày';
        }
        if ($months > 0) {
            return 'Còn '.$months.' tháng';
        }

        return 'Còn '.$days.' ngày';
    }

    public function serviceOwnerId(): int
    {
        return (int) ($this->parent_user_id ?: $this->id);
    }
}
