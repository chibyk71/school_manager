<?php

namespace App\Support\Permission;

use App\Models\Permission;
use Illuminate\Support\Collection;

/**
 * Application-owned permission catalogue synchronization (Permission Phase 2).
 *
 * Permissions are system-defined. This helper upserts the catalogue from
 * developer-owned definitions without exposing administrator CRUD.
 *
 * Seeders remain the primary deployment entry point; this class provides a
 * single, testable sync path that does not create duplicates.
 */
final class PermissionCatalogue
{
    /**
     * Synchronize permission definitions by stable name.
     *
     * Each definition must include at least `name`. Optional: display_name, description.
     * Unknown catalogue rows are left in place (no destructive prune in Phase 2).
     *
     * @param  iterable<int, array{name: string, display_name?: string|null, description?: string|null}>  $definitions
     * @return Collection<int, Permission>
     */
    public static function sync(iterable $definitions): Collection
    {
        $synced = collect();

        foreach ($definitions as $definition) {
            $name = $definition['name'] ?? null;
            if (! is_string($name) || $name === '') {
                throw new \InvalidArgumentException('Permission definition requires a non-empty name.');
            }

            $permission = Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $definition['display_name'] ?? null,
                    'description' => $definition['description'] ?? null,
                ]
            );

            $synced->push($permission);
        }

        return $synced;
    }
}
