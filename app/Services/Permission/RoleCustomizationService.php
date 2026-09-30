<?php

/**
 * Permission Phase 3 — role customization (copy) and safe deletion lifecycle.
 *
 * Customization copies a tenant/global role into an independent school-local role.
 * No parent/source/provenance columns. Permission associations are independent after copy.
 *
 * Deletion is blocked when the exact role_id has assignments in role_user.
 *
 * Concurrency protocol (same lock order as AcademicPeriodLock for periods):
 *
 *   Role lifecycle (customize / delete):
 *     BEGIN
 *       lockRole(roleId)              // roles row FOR UPDATE
 *       re-check assignment / local-duplicate invariants
 *       mutate
 *     COMMIT
 *
 *   Future assignment (Phase 6 User Management):
 *     BEGIN  (owning service transaction)
 *       lockRole(roleId)              // same roles row FOR UPDATE
 *       insert role_user
 *     COMMIT
 *
 * Holding the roles row lock serializes lifecycle checks with concurrent
 * assignment so a role_user row cannot appear after the assignment check
 * observed empty and before the role is deleted (or customized).
 *
 * lockRole() is public so Phase 6 can share the protocol without a second
 * locking subsystem. Call only inside an open DB transaction.
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
     * Acquire the role-level serialization lock used by customize/delete and
     * (future) assignment. Call only inside an open DB transaction.
     *
     * @throws ValidationException when the role no longer exists
     */
    public function lockRole(string $roleId): Role
    {
        $locked = Role::query()
            ->whereKey($roleId)
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            throw ValidationException::withMessages([
                'role' => 'Role does not exist.',
            ]);
        }

        return $locked;
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
        $roleId = (string) $tenantRole->getKey();

        return DB::transaction(function () use ($roleId, $schoolId) {
            $locked = $this->lockRole($roleId);

            if (! $locked->isTenant()) {
                throw ValidationException::withMessages([
                    'role' => 'Only tenant/global roles (school_id = NULL) can be customized.',
                ]);
            }

            $name = (string) $locked->name;

            // Local duplicate check under the tenant-role lock (DB unique is final safety).
            if (Role::query()->forSchool($schoolId)->where('name', $name)->exists()) {
                throw ValidationException::withMessages([
                    'role' => "School already has a local role named [{$name}].",
                ]);
            }

            // Assignment check after role lock: concurrent assignment must also
            // lock this role row (Phase 6), so it cannot land between check and create.
            if ($this->hasAssignmentsInSchool($locked, $schoolId)) {
                throw ValidationException::withMessages([
                    'role' => 'Cannot customize: the tenant role is already assigned to users in this school. Resolve assignments first.',
                ]);
            }

            $local = Role::create([
                'name' => $locked->name,
                'display_name' => $locked->display_name,
                'description' => $locked->description,
                'disabled' => $locked->disabled,
                'school_id' => $schoolId,
            ]);

            $permissionIds = $locked->permissions()->pluck('permissions.id')->all();
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

        $roleId = (string) $role->getKey();

        DB::transaction(function () use ($roleId) {
            $locked = $this->lockRole($roleId);

            // Assignment check after role lock: concurrent assignment must also
            // lock this role row (Phase 6). Prevents CASCADE wiping a race-inserted row.
            if ($this->hasAnyAssignments($locked)) {
                throw ValidationException::withMessages([
                    'role' => 'Cannot delete role while it is assigned to users. Remove assignments first.',
                ]);
            }

            $locked->permissions()->detach();
            $locked->delete();
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
