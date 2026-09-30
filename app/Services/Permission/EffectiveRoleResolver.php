<?php

/**
 * Permission Phase 3 — centralized effective role resolution.
 *
 * For a target school:
 *   local same-name role shadows tenant/global role (including when local is disabled).
 * Exactly one effective role per name. No N+1: two bulk queries + in-memory merge.
 *
 * Read-only: does not create, update, or delete role rows.
 */

namespace App\Services\Permission;

use App\Models\Role;
use App\Models\School;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class EffectiveRoleResolver
{
    /**
     * Full management catalogue: enabled + disabled effective roles (one per name).
     *
     * @return Collection<int, EffectiveRole>
     */
    public function resolveForSchool(School|string $school): Collection
    {
        $schoolId = $this->resolveSchoolId($school);

        $tenantRoles = Role::query()
            ->tenant()
            ->get()
            ->keyBy(fn (Role $role) => (string) $role->name);

        $localRoles = Role::query()
            ->forSchool($schoolId)
            ->get()
            ->keyBy(fn (Role $role) => (string) $role->name);

        $effective = collect();

        foreach ($tenantRoles as $name => $tenantRole) {
            if ($localRoles->has($name)) {
                $effective->push(new EffectiveRole($localRoles->get($name), EffectiveRole::ORIGIN_LOCAL));
            } else {
                $effective->push(new EffectiveRole($tenantRole, EffectiveRole::ORIGIN_TENANT));
            }
        }

        foreach ($localRoles as $name => $localRole) {
            if (! $tenantRoles->has($name)) {
                $effective->push(new EffectiveRole($localRole, EffectiveRole::ORIGIN_LOCAL));
            }
        }

        return $effective->values();
    }

    /**
     * Assignment catalogue: enabled effective roles only.
     *
     * @return Collection<int, EffectiveRole>
     */
    public function assignableForSchool(School|string $school): Collection
    {
        return $this->resolveForSchool($school)
            ->filter(fn (EffectiveRole $effective) => $effective->isEnabled())
            ->values();
    }

    /**
     * Resolve a single effective role by name, or null if none.
     */
    public function resolveByName(School|string $school, string $name): ?EffectiveRole
    {
        return $this->resolveForSchool($school)
            ->first(fn (EffectiveRole $effective) => $effective->name() === $name);
    }

    private function resolveSchoolId(School|string $school): string
    {
        if ($school instanceof School) {
            $id = $school->getKey();
            if ($id === null || $id === '') {
                throw new InvalidArgumentException('School must be persisted (have an id) for role resolution.');
            }

            return (string) $id;
        }

        $id = trim($school);
        if ($id === '') {
            throw new InvalidArgumentException('School id must be a non-empty string.');
        }

        return $id;
    }
}
