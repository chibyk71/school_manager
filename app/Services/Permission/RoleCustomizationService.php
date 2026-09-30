<?php

/**
 * Permission Phase 3 — role customization (copy) and safe deletion lifecycle.
 *
 * Customization copies a tenant/global role into an independent school-local role.
 * No parent/source/provenance columns. Permission associations are independent after copy.
 *
 * Deletion is blocked when the exact role_id has assignments in role_user.
 */

namespace App\Services\Permission;

use App\Models\Role;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleCustomizationService
{
    public function __construct(
        private readonly EffectiveRoleResolver $resolver,
    ) {
    }

    /**
     * Copy a tenant/global role into a school-local role with independent permissions.
     *
     * @throws ValidationException when preconditions fail
     */
    public function customize(Role $tenantRole, School $school): Role
    {
        $this->assertPersistedSchool($school);

        if (! $tenantRole->exists || $tenantRole->getKey() === null) {
            throw ValidationException::withMessages([
                'role' => 'Source role does not exist.',
            ]);
        }

        if (! $tenantRole->isTenant()) {
            throw ValidationException::withMessages([
                'role' => 'Only tenant/global roles (school_id = NULL) can be customized.',
            ]);
        }

        $schoolId = (string) $school->getKey();
        $name = (string) $tenantRole->name;

        if (Role::query()->forSchool($schoolId)->where('name', $name)->exists()) {
            throw ValidationException::withMessages([
                'role' => "School already has a local role named [{$name}].",
            ]);
        }

        if ($this->hasAssignmentsInSchool($tenantRole, $schoolId)) {
            throw ValidationException::withMessages([
                'role' => 'Cannot customize: the tenant role is already assigned to users in this school. Resolve assignments first.',
            ]);
        }

        return DB::transaction(function () use ($tenantRole, $schoolId) {
            // Re-check uniqueness inside the transaction (final protection is DB unique index).
            if (Role::query()->forSchool($schoolId)->where('name', $tenantRole->name)->exists()) {
                throw ValidationException::withMessages([
                    'role' => "School already has a local role named [{$tenantRole->name}].",
                ]);
            }

            $local = Role::create([
                'name' => $tenantRole->name,
                'display_name' => $tenantRole->display_name,
                'description' => $tenantRole->description,
                'disabled' => $tenantRole->disabled,
                'school_id' => $schoolId,
            ]);

            $permissionIds = $tenantRole->permissions()->pluck('permissions.id')->all();
            if ($permissionIds !== []) {
                $local->permissions()->sync($permissionIds);
            }

            return $local->refresh();
        });
    }

    /**
     * Delete a role when it has no assignments to that exact role_id.
     *
     * Local deletion: if a tenant same-name role exists, it becomes effective again.
     * Tenant deletion: does not modify local same-name roles.
     *
     * @throws ValidationException when assignments exist
     */
    public function delete(Role $role): void
    {
        if (! $role->exists || $role->getKey() === null) {
            throw ValidationException::withMessages([
                'role' => 'Role does not exist.',
            ]);
        }

        if ($this->hasAnyAssignments($role)) {
            throw ValidationException::withMessages([
                'role' => 'Cannot delete role while it is assigned to users. Remove assignments first.',
            ]);
        }

        DB::transaction(function () use ($role) {
            $role->permissions()->detach();
            $role->delete();
        });
    }

    /**
     * Disable a role without touching assignments or permissions.
     */
    public function disable(Role $role): Role
    {
        $role->disabled = true;
        $role->save();

        return $role->refresh();
    }

    /**
     * Re-enable a role (restores assignability via assignableForSchool).
     */
    public function enable(Role $role): Role
    {
        $role->disabled = false;
        $role->save();

        return $role->refresh();
    }

    /**
     * True when the exact role_id is assigned in the given school (role_user.school_id).
     */
    public function hasAssignmentsInSchool(Role $role, string $schoolId): bool
    {
        return DB::table('role_user')
            ->where('role_id', $role->getKey())
            ->where('school_id', $schoolId)
            ->exists();
    }

    /**
     * True when the exact role_id appears in any role_user row (any school scope).
     */
    public function hasAnyAssignments(Role $role): bool
    {
        return DB::table('role_user')
            ->where('role_id', $role->getKey())
            ->exists();
    }

    private function assertPersistedSchool(School $school): void
    {
        if ($school->getKey() === null || $school->getKey() === '') {
            throw ValidationException::withMessages([
                'school' => 'Target school must be persisted (have an id).',
            ]);
        }
    }
}
