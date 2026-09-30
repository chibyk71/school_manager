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
 */

namespace App\Services\Permission;

use App\Contracts\Authorization\Authorization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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

        if ($this->isPlatformAdmin($user)) {
            return true;
        }

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
     */
    private function resolveTargetSchoolId(?string $schoolId): ?string
    {
        if ($schoolId !== null) {
            $id = trim($schoolId);

            return $id === '' ? null : $id;
        }

        try {
            $active = app('schoolManager')->getActiveSchool();
            if ($active !== null && $active->getKey() !== null) {
                return (string) $active->getKey();
            }
        } catch (\Throwable) {
            // schoolManager may be unavailable in isolated unit tests
        }

        return null;
    }

    private function normalizePermission(string $permission): string
    {
        return trim($permission);
    }

    /**
     * Platform-level bypass: system-admin / super-admin style roles assigned
     * at tenant scope. Matches CustomUserChecker system-admin bypass intent
     * without requiring Laratrust checker internals.
     */
    private function isPlatformAdmin(User $user): bool
    {
        $adminNames = ['system-admin', 'super-admin', 'superadministrator'];

        $assigned = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->getKey())
            ->whereNull('role_user.school_id')
            ->whereIn('roles.name', $adminNames)
            ->exists();

        return $assigned;
    }

    /**
     * Direct permission_user grants at target school and/or tenant.
     */
    private function hasDirectPermission(User $user, string $permission, ?string $targetSchoolId): bool
    {
        $query = DB::table('permission_user')
            ->join('permissions', 'permissions.id', '=', 'permission_user.permission_id')
            ->where('permission_user.user_id', $user->getKey());

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
            if (Str::is($permission, (string) $name) || Str::is((string) $name, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Permissions inherited through assigned roles, resolved via effective
     * role definitions for the target school (local shadows tenant).
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
            foreach ($roleNames as $name) {
                $effective = $this->effectiveRoleResolver->resolveByName($targetSchoolId, $name);
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
        foreach ($roleNames as $name) {
            $role = Role::query()
                ->tenant()
                ->where('name', $name)
                ->first();

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
     * Role names the user is assigned at the target scope (school and/or tenant).
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function assignedRoleNames(User $user, ?string $targetSchoolId)
    {
        $query = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->getKey());

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
        // Prefer in-memory relation if already loaded; otherwise query pivot.
        if ($role->relationLoaded('permissions')) {
            foreach ($role->permissions as $perm) {
                if (Str::is($permission, (string) $perm->name) || Str::is((string) $perm->name, $permission)) {
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
            if (Str::is($permission, (string) $name) || Str::is((string) $name, $permission)) {
                return true;
            }
        }

        return false;
    }
}
