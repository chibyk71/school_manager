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
 * Stable application identity for stored content (File Manager Phase 2).
 *
 * Identity is independent of physical path, storage key, and Spatie media ID.
 * Secrets from the storage provider must never appear in ordinary API responses.
 */
class File extends Model
{
    use HasFactory;
    use HasUuids;

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
    ];

    protected $casts = [
        'ownership' => Ownership::class,
        'managed_by' => ManagedBy::class,
        'size_bytes' => 'integer',
    ];

    /**
     * Never expose the opaque storage key or provider secrets through default serialization.
     * Controllers/resources decide what metadata is safe.
     */
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

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
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
}
