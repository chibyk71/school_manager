<?php

/**
 * Permission Phase 4 — application authorization service.
 *
 * Effective capabilities =
 *   direct permissions (permission_user at target scope + tenant)
 *   + permissions inherited through effective roles (Phase 3 resolver)
 *
 * Scopes: tenant (school_id = null) and school (specific school_id).
 * No section-level authorization. No role-name authorization decisions.
 * No DENY permissions. Explicit school target never mutates context.
 *
 * Roles have no inherent authorization meaning: capability decisions use
 * permission identity only (never role names such as system-admin).
 */

namespace App\Services\Permission;

use App\Contracts\Authorization\Authorization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthorizationService implements Authorization
{
    public function __construct(
        private readonly EffectiveRoleResolver $effectiveRoleResolver,
    ) {
    }

    public function allows(User $user, string $permission, ?string $schoolId = null): bool
    {
        $permission = $this->normalizePermission($permission);
        if ($permission === '') {
            return false;
        }

        $targetSchoolId = $this->resolveTargetSchoolId($schoolId);

        if ($this->hasDirectPermission($user, $permission, $targetSchoolId)) {
            return true;
        }

        return $this->hasRoleDerivedPermission($user, $permission, $targetSchoolId);
    }

    public function denies(User $user, string $permission, ?string $schoolId = null): bool
    {
        return ! $this->allows($user, $permission, $schoolId);
    }

    /**
     * Resolve authorization target.
     *
     * Explicit $schoolId wins and does not touch active school / session.
     * When omitted, use schoolManager active school if present; otherwise
     * tenant scope (null).
     *
     * Unexpected failures from schoolManager (e.g. misconfiguration) are not
     * swallowed: only the legitimate "no active school" case yields tenant scope.
     */
    private function resolveTargetSchoolId(?string $schoolId): ?string
    {
        if ($schoolId !== null) {
            $id = trim($schoolId);

            return $id === '' ? null : $id;
        }

        if (! app()->bound('schoolManager')) {
            return null;
        }

        $active = app('schoolManager')->getActiveSchool();
        if ($active !== null && $active->getKey() !== null) {
            return (string) $active->getKey();
        }

        return null;
    }

    private function normalizePermission(string $permission): string
    {
        return trim($permission);
    }

    /**
     * Permission match semantics (Laratrust-aligned):
     * - exact name match, or
     * - requested string is a Str::is pattern against the stored name
     *   (e.g. request "students.*" matches stored "students.view").
     *
     * Stored wildcards are not treated as patterns against a concrete request
     * (no reverse Str::is). That keeps the canonical API from inventing a
     * second permission language beyond the established Laratrust direction.
     */
    private function permissionMatches(string $requested, string $stored): bool
    {
        return $requested === $stored || Str::is($requested, $stored);
    }

    /**
     * Direct permission_user grants at target school and/or tenant.
     * Constrains polymorphic user_type to User::class.
     */
    private function hasDirectPermission(User $user, string $permission, ?string $targetSchoolId): bool
    {
        $query = DB::table('permission_user')
            ->join('permissions', 'permissions.id', '=', 'permission_user.permission_id')
            ->where('permission_user.user_id', $user->getKey())
            ->where('permission_user.user_type', User::class);

        if ($targetSchoolId !== null) {
            $query->where(function ($q) use ($targetSchoolId) {
                $q->where('permission_user.school_id', $targetSchoolId)
                    ->orWhereNull('permission_user.school_id');
            });
        } else {
            $query->whereNull('permission_user.school_id');
        }

        $names = $query->pluck('permissions.name');

        foreach ($names as $name) {
            if ($this->permissionMatches($permission, (string) $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Permissions inherited through assigned roles, resolved via effective
     * role definitions for the target school (local shadows tenant).
     *
     * Catalogue is resolved once per call (Phase 3 bulk resolver), then
     * indexed by name — not resolveByName per assignment.
     *
     * Disabled effective roles do not contribute capabilities.
     */
    private function hasRoleDerivedPermission(User $user, string $permission, ?string $targetSchoolId): bool
    {
        $roleNames = $this->assignedRoleNames($user, $targetSchoolId);

        if ($roleNames->isEmpty()) {
            return false;
        }

        if ($targetSchoolId !== null) {
            $byName = $this->effectiveCatalogueByName($targetSchoolId);

            foreach ($roleNames as $name) {
                /** @var EffectiveRole|null $effective */
                $effective = $byName->get($name);
                if ($effective === null || $effective->isDisabled()) {
                    continue;
                }

                if ($this->roleHasPermission($effective->role, $permission)) {
                    return true;
                }
            }

            return false;
        }

        // Tenant context: only tenant role definitions (school_id null).
        $tenantByName = Role::query()
            ->tenant()
            ->whereIn('name', $roleNames->all())
            ->get()
            ->keyBy(fn (Role $role) => (string) $role->name);

        foreach ($roleNames as $name) {
            $role = $tenantByName->get($name);
            if ($role === null || $role->isDisabled()) {
                continue;
            }

            if ($this->roleHasPermission($role, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Single Phase 3 catalogue resolve, keyed by role name.
     *
     * @return Collection<string, EffectiveRole>
     */
    private function effectiveCatalogueByName(string $schoolId): Collection
    {
        return $this->effectiveRoleResolver
            ->resolveForSchool($schoolId)
            ->keyBy(fn (EffectiveRole $effective) => $effective->name());
    }

    /**
     * Role names the user is assigned at the target scope (school and/or tenant).
     * Constrains polymorphic user_type to User::class.
     *
     * @return Collection<int, string>
     */
    private function assignedRoleNames(User $user, ?string $targetSchoolId): Collection
    {
        $query = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->getKey())
            ->where('role_user.user_type', User::class);

        if ($targetSchoolId !== null) {
            $query->where(function ($q) use ($targetSchoolId) {
                $q->where('role_user.school_id', $targetSchoolId)
                    ->orWhereNull('role_user.school_id');
            });
        } else {
            $query->whereNull('role_user.school_id');
        }

        return $query->pluck('roles.name')->unique()->values();
    }

    private function roleHasPermission(Role $role, string $permission): bool
    {
        if ($role->relationLoaded('permissions')) {
            foreach ($role->permissions as $perm) {
                if ($this->permissionMatches($permission, (string) $perm->name)) {
                    return true;
                }
            }

            return false;
        }

        $names = DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permission_role.role_id', $role->getKey())
            ->pluck('permissions.name');

        foreach ($names as $name) {
            if ($this->permissionMatches($permission, (string) $name)) {
                return true;
            }
        }

        return false;
    }
}
