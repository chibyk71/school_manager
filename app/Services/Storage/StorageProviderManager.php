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
            // School context: own school providers only — never system config.
            return $query->forSchool($schoolId)->get();
        }

        // Platform context: system providers only.
        return $query->system()->get();
    }

    /**
     * Resolve a provider by id only if it is visible in the given context.
     */
    public function findForContext(string $id, ?string $schoolId): ?StorageProvider
    {
        $provider = StorageProvider::query()->find($id);
        if ($provider === null) {
            return null;
        }

        if ($schoolId !== null) {
            // School context: only matching school-owned rows.
            if ($provider->isSchoolOwned() && $provider->school_id === $schoolId) {
                return $provider;
            }

            return null;
        }

        // Platform context: system-owned only.
        if ($provider->isSystemOwned()) {
            return $provider;
        }

        return null;
    }

    /**
     * Whether the context may mutate (create/update/test) providers of the given ownership.
     */
    public function canMutateOwnership(?string $schoolId, StorageProviderOwnership $ownership): bool
    {
        if ($ownership === StorageProviderOwnership::System) {
            // System mutation only outside a school context.
            return $schoolId === null;
        }

        // School-owned mutation requires an active school context.
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
            if (array_key_exists('name', $validated)) {
                $provider->name = $validated['name'];
            }
            if (array_key_exists('enabled', $validated)) {
                $provider->enabled = (bool) $validated['enabled'];
            }
            if (array_key_exists('config', $validated)) {
                $provider->config = $this->sanitizeConfig($provider->driver, $validated['config'] ?? []);
            }

            if (! empty($validated['clear_secrets'])) {
                $provider->clearSecrets();
            } elseif (array_key_exists('secrets', $validated) && is_array($validated['secrets'])) {
                $provider->mergeSecretReplacement(
                    $this->sanitizeSecrets($provider->driver, $validated['secrets'])
                );
            }

            $provider->save();

            return $provider->fresh();
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
