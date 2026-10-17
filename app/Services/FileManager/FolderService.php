<?php

namespace App\Services\FileManager;

use App\Enums\FileManager\Ownership;
use App\Models\FileManager\Folder;
use App\Models\StorageProvider;
use App\Services\Storage\StorageProviderManager;
use App\Support\FileManager\ModuleDefinitionRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Folder hierarchy operations with ownership, cycle, and capability enforcement.
 */
final class FolderService
{
    public function __construct(
        private readonly StorageProviderManager $storageProviders,
        private readonly ModuleConfigurationService $modules,
    ) {}

    /**
     * @return Collection<int, Folder>
     */
    public function listForContext(?string $schoolId, ?string $parentId = null): Collection
    {
        $query = Folder::query()->orderBy('name');

        if ($schoolId !== null) {
            $query->forSchool($schoolId);
        } else {
            $query->system();
        }

        if ($parentId === null) {
            $query->roots();
        } else {
            $query->where('parent_id', $parentId);
        }

        return $query->get();
    }

    public function findForContext(string $id, ?string $schoolId): ?Folder
    {
        $folder = Folder::query()->find($id);
        if ($folder === null) {
            return null;
        }

        if ($schoolId !== null) {
            if ($folder->isSchoolOwned() && $folder->school_id === $schoolId) {
                return $folder;
            }

            return null;
        }

        return $folder->isSystemOwned() ? $folder : null;
    }

    /**
     * @param  array{
     *     name: string,
     *     parent_id?: string|null,
     *     module_code?: string|null,
     *     default_storage_provider_id?: string|null,
     *     capabilities?: list<string>|null,
     *     ownership?: string
     * }  $data
     */
    public function create(array $data, ?string $actingSchoolId): Folder
    {
        $ownership = $this->resolveOwnership($data['ownership'] ?? null, $actingSchoolId);
        $this->assertCanMutateOwnership($actingSchoolId, $ownership);

        $parent = null;
        if (! empty($data['parent_id'])) {
            $parent = $this->findForContext((string) $data['parent_id'], $actingSchoolId);
            if ($parent === null) {
                throw new InvalidArgumentException('Parent folder not found in the current ownership context.');
            }
            $this->assertSameOwnershipBoundary($ownership, $actingSchoolId, $parent);
        }

        $moduleCode = $data['module_code'] ?? $parent?->module_code;
        if ($moduleCode !== null && $moduleCode !== '') {
            ModuleDefinitionRegistry::require($moduleCode);
            if ($actingSchoolId !== null && ! $this->modules->isEnabled($actingSchoolId, $moduleCode)) {
                throw new InvalidArgumentException("Module '{$moduleCode}' is disabled for this school.");
            }
        } else {
            $moduleCode = null;
        }

        $capabilities = $this->resolveCapabilities(
            $data['capabilities'] ?? null,
            $moduleCode,
            $parent
        );

        $providerId = $data['default_storage_provider_id'] ?? $parent?->default_storage_provider_id;
        if ($providerId !== null) {
            $this->assertProviderInContext((string) $providerId, $actingSchoolId);
        }

        $folder = new Folder([
            'ownership' => $ownership,
            'school_id' => $ownership === Ownership::School ? $actingSchoolId : null,
            'parent_id' => $parent?->id,
            'name' => trim((string) $data['name']),
            'module_code' => $moduleCode,
            'default_storage_provider_id' => $providerId,
            'capabilities' => $capabilities,
        ]);
        $folder->save();

        return $folder->fresh();
    }

    /**
     * @param  array{name?: string, default_storage_provider_id?: string|null, capabilities?: list<string>|null}  $data
     */
    public function update(Folder $folder, array $data, ?string $actingSchoolId): Folder
    {
        $this->assertFolderInContext($folder, $actingSchoolId);

        if (array_key_exists('name', $data)) {
            $folder->name = trim((string) $data['name']);
        }

        if (array_key_exists('default_storage_provider_id', $data)) {
            $providerId = $data['default_storage_provider_id'];
            if ($providerId !== null) {
                $this->assertProviderInContext((string) $providerId, $actingSchoolId);
            }
            // Changing default provider affects future uploads only; existing files keep their provider.
            $folder->default_storage_provider_id = $providerId;
        }

        if (array_key_exists('capabilities', $data) && is_array($data['capabilities'])) {
            $folder->capabilities = $this->resolveCapabilities(
                $data['capabilities'],
                $folder->module_code,
                $folder->parent
            );
        }

        $folder->save();

        return $folder->fresh();
    }

    public function move(Folder $folder, ?string $newParentId, ?string $actingSchoolId): Folder
    {
        $this->assertFolderInContext($folder, $actingSchoolId);

        if ($newParentId === $folder->id) {
            throw new InvalidArgumentException('A folder cannot be its own parent.');
        }

        $newParent = null;
        if ($newParentId !== null) {
            $newParent = $this->findForContext($newParentId, $actingSchoolId);
            if ($newParent === null) {
                throw new InvalidArgumentException('Target parent folder not found in the current ownership context.');
            }
            $this->assertSameOwnershipBoundary($folder->ownership, $folder->school_id, $newParent);

            if ($this->wouldCreateCycle($folder, $newParent)) {
                throw new InvalidArgumentException('Move would create a cycle in the folder hierarchy.');
            }
        }

        $folder->parent_id = $newParent?->id;
        $folder->save();

        return $folder->fresh();
    }

    public function delete(Folder $folder, ?string $actingSchoolId): void
    {
        $this->assertFolderInContext($folder, $actingSchoolId);

        if (! $folder->isEmpty()) {
            throw new InvalidArgumentException('Cannot delete a folder that still contains child folders or files.');
        }

        $folder->delete();
    }

    public function wouldCreateCycle(Folder $folder, Folder $prospectiveParent): bool
    {
        $current = $prospectiveParent;
        $guard = 0;
        while ($current !== null) {
            if ($current->id === $folder->id) {
                return true;
            }
            if ($current->parent_id === null) {
                break;
            }
            $current = Folder::query()->find($current->parent_id);
            if (++$guard > 10000) {
                // Pathological depth protection for the cycle walk only; not a product depth limit.
                return true;
            }
        }

        return false;
    }

    private function resolveOwnership(?string $requested, ?string $actingSchoolId): Ownership
    {
        if ($requested !== null && $requested !== '') {
            $ownership = Ownership::from($requested);
            if ($ownership === Ownership::System && $actingSchoolId !== null) {
                throw new InvalidArgumentException('Cannot create system-owned folders from a school context.');
            }
            if ($ownership === Ownership::School && $actingSchoolId === null) {
                throw new InvalidArgumentException('Cannot create school-owned folders without a school context.');
            }

            return $ownership;
        }

        return $actingSchoolId !== null ? Ownership::School : Ownership::System;
    }

    private function assertCanMutateOwnership(?string $actingSchoolId, Ownership $ownership): void
    {
        if ($ownership === Ownership::System && $actingSchoolId !== null) {
            throw new InvalidArgumentException('System-owned folders cannot be managed from a school context.');
        }
        if ($ownership === Ownership::School && $actingSchoolId === null) {
            throw new InvalidArgumentException('School-owned folders cannot be managed outside their school context.');
        }
    }

    private function assertFolderInContext(Folder $folder, ?string $actingSchoolId): void
    {
        if ($actingSchoolId !== null) {
            if (! $folder->isSchoolOwned() || $folder->school_id !== $actingSchoolId) {
                throw new InvalidArgumentException('Folder is not accessible in the current school context.');
            }

            return;
        }

        if (! $folder->isSystemOwned()) {
            throw new InvalidArgumentException('Folder is not accessible in the platform context.');
        }
    }

    private function assertSameOwnershipBoundary(Ownership $ownership, ?string $schoolId, Folder $other): void
    {
        if ($other->ownership !== $ownership) {
            throw new InvalidArgumentException('Parent and child folders must share the same ownership boundary.');
        }
        if ($ownership === Ownership::School && $other->school_id !== $schoolId) {
            throw new InvalidArgumentException('Parent and child folders must belong to the same school.');
        }
    }

    private function assertProviderInContext(string $providerId, ?string $actingSchoolId): void
    {
        $provider = $this->storageProviders->findForContext($providerId, $actingSchoolId);
        if ($provider === null) {
            throw new InvalidArgumentException('Storage provider not found in the current ownership context.');
        }
        if (! $provider->enabled) {
            throw new InvalidArgumentException('Storage provider is disabled.');
        }
    }

    /**
     * @param  list<string>|null  $requested
     * @return list<string>
     */
    private function resolveCapabilities(?array $requested, ?string $moduleCode, ?Folder $parent): array
    {
        if ($moduleCode === null || $moduleCode === '') {
            // No module: allow full set if explicitly requested, else inherit parent or empty.
            if ($requested !== null) {
                return array_values(array_unique(array_filter($requested, 'is_string')));
            }

            return $parent?->capabilityList() ?? [];
        }

        $definition = ModuleDefinitionRegistry::require($moduleCode);

        if ($requested === null) {
            $requested = $parent?->capabilityList() ?? $definition->maxCapabilityValues();
        }

        if (! $definition->acceptsCapabilities($requested)) {
            throw new InvalidArgumentException(
                "Folder capabilities exceed the maximum allowed for module '{$moduleCode}'."
            );
        }

        return $definition->clampCapabilities($requested);
    }
}
