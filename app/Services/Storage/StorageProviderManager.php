<?php

namespace App\Services\Storage;

use App\Enums\StorageProviderDriver;
use App\Enums\StorageProviderOwnership;
use App\Models\StorageProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Application service for storage provider CRUD within authorization boundaries.
 *
 * Visibility and mutation rules (Phase 1 review):
 *
 * - School context (active school_id set):
 *   - Lists and resolves only school-owned providers for that school.
 *   - Must not see, create, update, or test system-owned providers.
 *
 * - Platform / tenant context (no active school):
 *   - Lists and resolves only system-owned providers.
 *   - Must not create school-owned providers without a school context.
 *
 * Controllers still enforce permission gates; this service enforces ownership
 * isolation so generic storage-providers.manage cannot cross the boundary.
 */
final class StorageProviderManager
{
    public function __construct(
        private readonly StorageProviderValidator $validator,
        private readonly StorageProviderCapabilityReporter $capabilities,
        private readonly StorageProviderDependencyChecker $dependencies,
    ) {}

    /**
     * Providers visible in the current authorization context.
     *
     * @return Collection<int, StorageProvider>
     */
    public function listForContext(?string $schoolId): Collection
    {
        $query = StorageProvider::query()->orderBy('name');

        if ($schoolId !== null) {
            return $query->forSchool($schoolId)->get();
        }

        return $query->system()->get();
    }

    public function findForContext(string $id, ?string $schoolId): ?StorageProvider
    {
        $provider = StorageProvider::query()->find($id);
        if ($provider === null) {
            return null;
        }

        if ($schoolId !== null) {
            if ($provider->isSchoolOwned() && $provider->school_id === $schoolId) {
                return $provider;
            }

            return null;
        }

        if ($provider->isSystemOwned()) {
            return $provider;
        }

        return null;
    }

    public function canMutateOwnership(?string $schoolId, StorageProviderOwnership $ownership): bool
    {
        if ($ownership === StorageProviderOwnership::System) {
            return $schoolId === null;
        }

        return $schoolId !== null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $actingSchoolId): StorageProvider
    {
        // Ownership from payload first so school_id can be injected before validation.
        $rawOwnership = $data['ownership'] ?? null;
        $ownershipHint = $rawOwnership instanceof StorageProviderOwnership
            ? $rawOwnership
            : (is_string($rawOwnership) ? StorageProviderOwnership::tryFrom($rawOwnership) : null);

        if ($ownershipHint === StorageProviderOwnership::School && $actingSchoolId !== null) {
            $data['school_id'] = $actingSchoolId;
        }
        if ($ownershipHint === StorageProviderOwnership::System) {
            unset($data['school_id']);
        }

        $validated = $this->validator->validate($data);

        $ownership = StorageProviderOwnership::from(
            $validated['ownership'] instanceof StorageProviderOwnership
                ? $validated['ownership']->value
                : (string) $validated['ownership']
        );

        if (! $this->canMutateOwnership($actingSchoolId, $ownership)) {
            throw new \InvalidArgumentException(
                $ownership === StorageProviderOwnership::System
                    ? 'System-owned storage providers cannot be created from a school context.'
                    : 'School-owned storage providers require an active school context.'
            );
        }

        $driver = StorageProviderDriver::from(
            $validated['driver'] instanceof StorageProviderDriver
                ? $validated['driver']->value
                : (string) $validated['driver']
        );

        $schoolId = $ownership->requiresSchool() ? $actingSchoolId : null;

        return DB::transaction(function () use ($validated, $ownership, $driver, $schoolId) {
            $provider = new StorageProvider([
                'name' => $validated['name'],
                'driver' => $driver,
                'ownership' => $ownership,
                'school_id' => $schoolId,
                'config' => $this->sanitizeConfig($driver, $validated['config'] ?? []),
                'enabled' => $validated['enabled'] ?? true,
            ]);

            if (! empty($validated['secrets'] ?? [])) {
                $provider->setEncryptedSecrets(
                    $this->sanitizeSecrets($driver, $validated['secrets'])
                );
            }

            $provider->save();

            return $provider->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(StorageProvider $provider, array $data, ?string $actingSchoolId): StorageProvider
    {
        if (! $this->canMutateOwnership($actingSchoolId, $provider->ownership)) {
            throw new \InvalidArgumentException(
                $provider->isSystemOwned()
                    ? 'System-owned storage providers cannot be modified from a school context.'
                    : 'School-owned storage providers cannot be modified outside their school context.'
            );
        }

        if ($provider->isSchoolOwned() && $provider->school_id !== $actingSchoolId) {
            throw new \InvalidArgumentException(
                'Cannot modify a storage provider owned by another school.'
            );
        }

        $validated = $this->validator->validate($data, isUpdate: true);

        return DB::transaction(function () use ($provider, $validated) {
            /** @var StorageProvider|null $locked */
            $locked = StorageProvider::query()->whereKey($provider->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new \InvalidArgumentException('Storage provider not found.');
            }

            // Location-affecting config / secrets cannot change while files depend on this provider.
            if ($this->dependencies->hasDependencies($locked)) {
                if (array_key_exists('config', $validated)) {
                    $incoming = $this->sanitizeConfig($locked->driver, $validated['config'] ?? []);
                    $current = is_array($locked->config) ? $locked->config : [];
                    if ($this->configChangeAffectsLocation($locked->driver, $current, $incoming)) {
                        throw new \InvalidArgumentException(
                            'Cannot change location-affecting provider configuration while File Manager files depend on this provider.'
                        );
                    }
                }
                if (! empty($validated['clear_secrets'])
                    || (array_key_exists('secrets', $validated) && is_array($validated['secrets']) && $validated['secrets'] !== [])) {
                    throw new \InvalidArgumentException(
                        'Cannot change provider credentials while File Manager files depend on this provider.'
                    );
                }
            }

            if (array_key_exists('name', $validated)) {
                $locked->name = $validated['name'];
            }
            if (array_key_exists('enabled', $validated)) {
                $locked->enabled = (bool) $validated['enabled'];
            }
            if (array_key_exists('config', $validated)) {
                $locked->config = $this->sanitizeConfig($locked->driver, $validated['config'] ?? []);
            }

            if (! empty($validated['clear_secrets'])) {
                $locked->clearSecrets();
            } elseif (array_key_exists('secrets', $validated) && is_array($validated['secrets'])) {
                $locked->mergeSecretReplacement(
                    $this->sanitizeSecrets($locked->driver, $validated['secrets'])
                );
            }

            $locked->save();

            return $locked->fresh();
        });
    }

    /**
     * Soft-delete a provider when no File records depend on it.
     */
    public function delete(StorageProvider $provider, ?string $actingSchoolId): void
    {
        if (! $this->canMutateOwnership($actingSchoolId, $provider->ownership)) {
            throw new \InvalidArgumentException(
                $provider->isSystemOwned()
                    ? 'System-owned storage providers cannot be deleted from a school context.'
                    : 'School-owned storage providers cannot be deleted outside their school context.'
            );
        }

        if ($provider->isSchoolOwned() && $provider->school_id !== $actingSchoolId) {
            throw new \InvalidArgumentException(
                'Cannot delete a storage provider owned by another school.'
            );
        }

        DB::transaction(function () use ($provider) {
            /** @var StorageProvider|null $locked */
            $locked = StorageProvider::query()->whereKey($provider->id)->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }

            if ($this->dependencies->hasDependencies($locked)) {
                throw new \InvalidArgumentException(
                    'Cannot delete a storage provider while File Manager files (including trash) depend on it.'
                );
            }

            $locked->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function capabilitiesFor(StorageProvider $provider): array
    {
        return $this->capabilities->report($provider->driver, $provider->connectionConfig());
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */

    /**
     * Keys that affect how an existing storage_key resolves to a physical object.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     */
    /**
     * Stable fingerprint of location-affecting provider configuration used for object placement.
     * Compared at file registration so concurrent reconfiguration cannot silently retarget metadata.
     */
    public function locationFingerprint(StorageProvider $provider): string
    {
        $driver = $provider->driver instanceof StorageProviderDriver
            ? $provider->driver
            : StorageProviderDriver::from((string) $provider->driver);

        $config = is_array($provider->config) ? $provider->config : [];
        $locationKeys = match ($driver) {
            StorageProviderDriver::Local => ['root'],
            StorageProviderDriver::S3 => ['bucket', 'region', 'endpoint', 'url', 'use_path_style_endpoint', 'key'],
        };

        $slice = [];
        foreach ($locationKeys as $key) {
            $slice[$key] = $config[$key] ?? null;
        }

        return hash('sha256', json_encode([$driver->value, $slice], JSON_THROW_ON_ERROR));
    }

    private function configChangeAffectsLocation(StorageProviderDriver $driver, array $current, array $incoming): bool
    {
        $locationKeys = match ($driver) {
            StorageProviderDriver::Local => ['root'],
            StorageProviderDriver::S3 => ['bucket', 'region', 'endpoint', 'url', 'use_path_style_endpoint', 'key'],
        };

        foreach ($locationKeys as $key) {
            if (! array_key_exists($key, $incoming)) {
                continue;
            }
            $old = $current[$key] ?? null;
            $new = $incoming[$key];
            if ((string) $old !== (string) $new) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeConfig(StorageProviderDriver $driver, array $config): array
    {
        $allowed = $driver->configKeys();
        $out = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $config)) {
                $out[$key] = $config[$key];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $secrets
     * @return array<string, mixed>
     */
    private function sanitizeSecrets(StorageProviderDriver $driver, array $secrets): array
    {
        $allowed = $driver->secretKeys();
        $out = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $secrets)) {
                $out[$key] = $secrets[$key];
            }
        }

        return $out;
    }
}
