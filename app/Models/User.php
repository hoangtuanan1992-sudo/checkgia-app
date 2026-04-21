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

#[Fillable(['name', 'email', 'password', 'role', 'parent_user_id', 'service_start_date', 'service_end_date', 'admin_note'])]
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
