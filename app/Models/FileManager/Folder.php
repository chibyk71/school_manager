<?php

namespace App\Models\FileManager;

use App\Enums\FileManager\Ownership;
use App\Models\Model;
use App\Models\School;
use App\Models\StorageProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * Logical folder node (File Manager Phase 2).
 *
 * Not a physical directory. Identity is stable across rename/move.
 * Ownership is explicit; parent and child must share the same ownership boundary.
 */
class Folder extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'fm_folders';

    protected $fillable = [
        'ownership',
        'school_id',
        'parent_id',
        'name',
        'module_code',
        'default_storage_provider_id',
        'capabilities',
    ];

    protected $casts = [
        'ownership' => Ownership::class,
        'capabilities' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Folder $folder): void {
            $folder->assertOwnershipInvariants();
        });
    }

    public function assertOwnershipInvariants(): void
    {
        $ownership = $this->ownership instanceof Ownership
            ? $this->ownership
            : Ownership::from((string) $this->ownership);

        if ($ownership->requiresSchool() && empty($this->school_id)) {
            throw new InvalidArgumentException('School-owned folders require a school_id.');
        }

        if ($ownership->forbidsSchool() && ! empty($this->school_id)) {
            throw new InvalidArgumentException('System-owned folders must not have a school_id.');
        }
    }

    public function isSystemOwned(): bool
    {
        return $this->ownership === Ownership::System;
    }

    public function isSchoolOwned(): bool
    {
        return $this->ownership === Ownership::School;
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'folder_id');
    }

    public function defaultStorageProvider(): BelongsTo
    {
        return $this->belongsTo(StorageProvider::class, 'default_storage_provider_id');
    }

    /**
     * @return list<string>
     */
    public function capabilityList(): array
    {
        $caps = $this->capabilities;

        return is_array($caps) ? array_values(array_filter($caps, 'is_string')) : [];
    }

    public function isEmpty(): bool
    {
        return ! $this->children()->exists() && ! $this->files()->exists();
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('ownership', Ownership::System->value);
    }

    public function scopeForSchool(Builder $query, string $schoolId): Builder
    {
        return $query->where('ownership', Ownership::School->value)
            ->where('school_id', $schoolId);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}
