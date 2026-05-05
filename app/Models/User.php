<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'email', 'password', 'role', 'parent_user_id', 'visible_product_group_ids', 'visible_competitor_site_group_ids', 'service_start_date', 'service_end_date', 'admin_note', 'product_limit', 'allow_compare_match', 'allow_shopee_check'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'service_start_date' => 'date',
            'service_end_date' => 'date',
            'product_limit' => 'integer',
            'visible_product_group_ids' => 'array',
            'visible_competitor_site_group_ids' => 'array',
            'allow_compare_match' => 'boolean',
            'allow_shopee_check' => 'boolean',
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

    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * @return array<int, int>
     */
    public function visibleProductGroupIds(): array
    {
        return $this->normalizedIdArray($this->visible_product_group_ids);
    }

    /**
     * @return array<int, int>
     */
    public function visibleCompetitorSiteGroupIds(): array
    {
        return $this->normalizedIdArray($this->visible_competitor_site_group_ids);
    }

    /**
     * @param mixed $value
     * @return array<int, int>
     */
    private function normalizedIdArray(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function shopeeCheckEnabledForId(int $userId): bool
    {
        if ($userId <= 0 || ! Schema::hasColumn('users', 'allow_shopee_check')) {
            return false;
        }

        return (bool) static::query()
            ->whereKey($userId)
            ->value('allow_shopee_check');
    }

    public static function compareMatchEnabledForId(int $userId): bool
    {
        if ($userId <= 0 || ! Schema::hasColumn('users', 'allow_compare_match')) {
            return false;
        }

        return (bool) static::query()
            ->whereKey($userId)
            ->value('allow_compare_match');
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
