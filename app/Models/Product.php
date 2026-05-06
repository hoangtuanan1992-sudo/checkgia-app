<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Fillable(['user_id', 'product_group_id', 'name', 'price', 'product_url', 'last_scraped_at', 'own_scrape_failed_since', 'deleted_by_user_id'])]
class Product extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_scraped_at' => 'datetime',
            'own_scrape_failed_since' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        if (static::hasSoftDeleteColumn()) {
            static::addGlobalScope(new SoftDeletingScope);
        }
    }

    public static function hasSoftDeleteColumn(): bool
    {
        return static::hasColumn('deleted_at');
    }

    public static function hasDeletedByColumn(): bool
    {
        return static::hasColumn('deleted_by_user_id');
    }

    public function getDeletedAtColumn(): string
    {
        return 'deleted_at';
    }

    public function getQualifiedDeletedAtColumn(): string
    {
        return $this->qualifyColumn($this->getDeletedAtColumn());
    }

    public function trashed(): bool
    {
        return ! is_null($this->{$this->getDeletedAtColumn()});
    }

    public function restore(): bool
    {
        if (! static::hasSoftDeleteColumn()) {
            return false;
        }

        $this->{$this->getDeletedAtColumn()} = null;
        $this->exists = true;

        return $this->save();
    }

    protected function performDeleteOnModel(): mixed
    {
        if (static::hasSoftDeleteColumn()) {
            $this->runSoftDelete();

            return null;
        }

        $this->setKeysForSaveQuery($this->newModelQuery())->delete();
        $this->exists = false;

        return null;
    }

    protected function runSoftDelete(): void
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());
        $time = $this->freshTimestamp();
        $columns = [$this->getDeletedAtColumn() => $this->fromDateTime($time)];

        $this->{$this->getDeletedAtColumn()} = $time;

        if ($this->usesTimestamps() && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;
            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        $query->update($columns);
        $this->syncOriginalAttributes(array_keys($columns));
    }

    private static function hasColumn(string $column): bool
    {
        try {
            return Schema::hasColumn((new static)->getTable(), $column);
        } catch (Throwable) {
            return false;
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class);
    }
}
