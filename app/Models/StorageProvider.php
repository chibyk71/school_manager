<?php

namespace App\Models;

use App\Enums\StorageProviderDriver;
use App\Enums\StorageProviderOwnership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use Throwable;

/**
 * StorageProvider — persisted storage destination configuration (File Manager Phase 1).
 *
 * Ownership is explicit (system | school). Never infer system from school_id IS NULL.
 * Secrets are encrypted at rest and never exposed in serialization by default.
 */
class StorageProvider extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'storage_providers';

    protected $fillable = [
        'name',
        'driver',
        'ownership',
        'school_id',
        'config',
        'secrets',
        'enabled',
    ];

    protected $hidden = [
        'secrets',
    ];

    protected $casts = [
        'driver' => StorageProviderDriver::class,
        'ownership' => StorageProviderOwnership::class,
        'config' => 'array',
        'enabled' => 'boolean',
        'deleted_via_school_cascade' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (StorageProvider $provider): void {
            $provider->assertOwnershipInvariants();
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function assertOwnershipInvariants(): void
    {
        $ownership = $this->ownership instanceof StorageProviderOwnership
            ? $this->ownership
            : StorageProviderOwnership::from((string) $this->ownership);

        if ($ownership->requiresSchool() && empty($this->school_id)) {
            throw new InvalidArgumentException(
                'School-owned storage providers require a school_id.'
            );
        }

        if ($ownership->forbidsSchool() && ! empty($this->school_id)) {
            throw new InvalidArgumentException(
                'System-owned storage providers must not have a school_id.'
            );
        }
    }

    public function isSystemOwned(): bool
    {
        return $this->ownership === StorageProviderOwnership::System;
    }

    public function isSchoolOwned(): bool
    {
        return $this->ownership === StorageProviderOwnership::School;
    }

    public function hasSecretsConfigured(): bool
    {
        $decoded = $this->getDecryptedSecrets();

        return is_array($decoded) && $decoded !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDecryptedSecrets(): array
    {
        if ($this->secrets === null || $this->secrets === '') {
            return [];
        }

        try {
            $plain = Crypt::decryptString($this->secrets);
            $decoded = json_decode($plain, true);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $secrets
     */
    public function setEncryptedSecrets(array $secrets): void
    {
        // Strip empty values so blank replacement fields do not wipe intentionally.
        $filtered = array_filter(
            $secrets,
            static fn ($value) => $value !== null && $value !== ''
        );

        if ($filtered === []) {
            $this->attributes['secrets'] = null;

            return;
        }

        $this->attributes['secrets'] = Crypt::encryptString(json_encode($filtered));
    }

    /**
     * Merge a partial secret replacement: blank keys preserve existing values.
     *
     * @param  array<string, mixed>  $incoming
     */
    public function mergeSecretReplacement(array $incoming): void
    {
        $current = $this->getDecryptedSecrets();
        $merged = $current;

        foreach ($incoming as $key => $value) {
            if ($value === null || $value === '') {
                continue; // preserve existing
            }
            $merged[$key] = $value;
        }

        $this->setEncryptedSecrets($merged);
    }

    /**
     * Explicitly clear all secrets (distinct from blank input).
     */
    public function clearSecrets(): void
    {
        $this->attributes['secrets'] = null;
    }

    /**
     * Full connection array for adapter construction (config + decrypted secrets).
     *
     * @return array<string, mixed>
     */
    public function connectionConfig(): array
    {
        return array_merge($this->config ?? [], $this->getDecryptedSecrets());
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('ownership', StorageProviderOwnership::System->value);
    }

    public function scopeForSchool(Builder $query, string $schoolId): Builder
    {
        return $query->where('ownership', StorageProviderOwnership::School->value)
            ->where('school_id', $schoolId);
    }
}
