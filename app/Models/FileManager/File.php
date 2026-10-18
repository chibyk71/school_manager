<?php

namespace App\Models\FileManager;

use App\Enums\FileManager\ManagedBy;
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
 * Stable application identity for stored content (File Manager Phase 2 + Phase 3).
 *
 * Phase 3 lifecycle (File Manager-managed only):
 * - active: trashed_at is null
 * - trashed: trashed_at set; physical object retained; purge_eligible_at immutable
 * - purge pipeline: purge_state pending | deleting_physical
 */
class File extends Model
{
    use HasFactory;
    use HasUuids;

    public const PURGE_STATE_PENDING = 'pending';

    public const PURGE_STATE_DELETING_PHYSICAL = 'deleting_physical';

    public const PURGE_STATE_FAILED = 'failed';

    protected $table = 'fm_files';

    protected $fillable = [
        'ownership',
        'school_id',
        'folder_id',
        'storage_provider_id',
        'storage_key',
        'original_filename',
        'mime_type',
        'size_bytes',
        'checksum',
        'managed_by',
        'module_code',
        'trashed_at',
        'original_folder_id',
        'purge_eligible_at',
        'purge_state',
        'lifecycle_version',
    ];

    protected $casts = [
        'ownership' => Ownership::class,
        'managed_by' => ManagedBy::class,
        'size_bytes' => 'integer',
        'trashed_at' => 'datetime',
        'purge_eligible_at' => 'datetime',
        'lifecycle_version' => 'integer',
    ];

    protected $hidden = [
        'storage_key',
    ];

    protected static function booted(): void
    {
        static::saving(function (File $file): void {
            $file->assertOwnershipInvariants();
        });
    }

    public function assertOwnershipInvariants(): void
    {
        $ownership = $this->ownership instanceof Ownership
            ? $this->ownership
            : Ownership::from((string) $this->ownership);

        if ($ownership->requiresSchool() && empty($this->school_id)) {
            throw new InvalidArgumentException('School-owned files require a school_id.');
        }

        if ($ownership->forbidsSchool() && ! empty($this->school_id)) {
            throw new InvalidArgumentException('System-owned files must not have a school_id.');
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

    public function isManagedByFileManager(): bool
    {
        return $this->managed_by === ManagedBy::FileManager;
    }

    public function isModuleManaged(): bool
    {
        return $this->managed_by === ManagedBy::Module;
    }

    public function isTrashed(): bool
    {
        return $this->trashed_at !== null;
    }

    public function isActive(): bool
    {
        return $this->trashed_at === null;
    }

    public function isPurgeEligible(): bool
    {
        if (! $this->isTrashed() || $this->purge_eligible_at === null) {
            return false;
        }

        return now()->greaterThanOrEqualTo($this->purge_eligible_at);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }

    public function originalFolder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'original_folder_id');
    }

    public function storageProvider(): BelongsTo
    {
        return $this->belongsTo(StorageProvider::class, 'storage_provider_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'file_id');
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('trashed_at');
    }

    public function scopeTrashed(Builder $query): Builder
    {
        return $query->whereNotNull('trashed_at')
            ->where('managed_by', ManagedBy::FileManager->value);
    }

    public function scopeEligibleForPurge(Builder $query): Builder
    {
        // Terminal failure (failed) is intentionally excluded so discovery cannot
        // requeue exhausted jobs forever. Operators clear failure for a safe retry.
        return $query->trashed()
            ->where('purge_eligible_at', '<=', now())
            ->where(function (Builder $q): void {
                $q->whereNull('purge_state')
                    ->orWhere('purge_state', self::PURGE_STATE_PENDING)
                    ->orWhere('purge_state', self::PURGE_STATE_DELETING_PHYSICAL);
            });
    }
}
