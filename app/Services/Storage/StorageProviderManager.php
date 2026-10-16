<?php

namespace App\Services\Storage;

use App\Enums\StorageProviderDriver;
use App\Enums\StorageProviderOwnership;
use App\Models\StorageProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Application service for storage provider CRUD within authorization boundaries.
 *
 * Controllers enforce permission; this service enforces ownership invariants
 * and secret handling.
 */
final class StorageProviderManager
{
    public function __construct(
        private readonly StorageProviderValidator $validator,
        private readonly StorageProviderCapabilityReporter $capabilities,
    ) {}

    /**
     * @return Collection<int, StorageProvider>
     */
    public function listForContext(?string $schoolId, bool $systemOnly = false): Collection
    {
        $query = StorageProvider::query()->orderBy('name');

        if ($systemOnly || $schoolId === null) {
            return $query->system()->get();
        }

        return $query->visibleToSchool($schoolId)->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): StorageProvider
    {
        $validated = $this->validator->validate($data);

        $ownership = StorageProviderOwnership::from(
            $validated['ownership'] instanceof StorageProviderOwnership
                ? $validated['ownership']->value
                : (string) $validated['ownership']
        );

        $driver = StorageProviderDriver::from(
            $validated['driver'] instanceof StorageProviderDriver
                ? $validated['driver']->value
                : (string) $validated['driver']
        );

        return DB::transaction(function () use ($validated, $ownership, $driver) {
            $provider = new StorageProvider([
                'name' => $validated['name'],
                'driver' => $driver,
                'ownership' => $ownership,
                'school_id' => $ownership->requiresSchool() ? ($validated['school_id'] ?? null) : null,
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
    public function update(StorageProvider $provider, array $data): StorageProvider
    {
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

    public function findForContext(string $id, ?string $schoolId, bool $allowSystemMutation = false): ?StorageProvider
    {
        $provider = StorageProvider::query()->find($id);
        if ($provider === null) {
            return null;
        }

        if ($provider->isSystemOwned()) {
            return $provider;
        }

        if ($schoolId !== null && $provider->school_id === $schoolId) {
            return $provider;
        }

        return null;
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
