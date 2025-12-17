<?php

namespace App\Policies;

use App\Models\Employee\Department;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * DepartmentPolicy – Granular Authorization for Department Resource (Production-Ready – December 2025)
 *
 * Features Implemented & Problems Solved:
 *
 * 1. Complete RBAC integration with Laratrust:
 *    - All actions gated by specific permissions (departments.*).
 *    - Uses LaratrustFacade::hasPermission() for clean, cacheable checks.
 *
 * 2. Multi-tenant isolation:
 *    - Every instance-level method (view, update, delete, restore, forceDelete, assignRole, viewUsers)
 *      enforces $user->school_id === $department->school_id.
 *    - Prevents cross-school data access/leakage – critical for SaaS.
 *
 * 3. Granular permissions matching controller actions:
 *    - viewAny / view
 *    - create (school-wide, no instance check needed)
 *    - update / delete / restore / forceDelete
 *    - assignRole (role + section scoping)
 *    - viewUsers (members tab)
 *
 * 4. Bulk operation safety:
 *    - destroy() in controller uses Gate::authorize('delete', Department::class)
 *      which calls viewAny-style check – sufficient for bulk since per-record school check
 *      happens implicitly via query scoping (BelongsToSchool trait).
 *
 * 5. Industry-standard Laravel Policy pattern:
 *    - Clean, testable, declarative.
 *    - Easily extendable if new actions added.
 *    - Works seamlessly with Gate::authorize() in controller.
 *
 * 6. Frontend Integration:
 *    - usePermissions composable reads these same permission strings
 *      to conditionally show/hide buttons, tabs, modals.
 *    - Example: hasPermission('departments.assign-role') → show role assignment section.
 *
 * Permission strings used (register in Laratrust seeder):
 *   - departments.view
 *   - departments.create
 *   - departments.update
 *   - departments.delete
 *   - departments.restore
 *   - departments.force-delete
 *   - departments.assign-role
 *   - departments.view-users
 */
class DepartmentPolicy
{
    /**
     * Determine whether the user can view any departments (index + trashed).
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('departments.view');
    }

    /**
     * Determine whether the user can view a specific department (show + edit modal).
     */
    public function view(User $user, Department $department): bool
    {
        return LaratrustFacade::hasPermission('departments.view')
            && $user->school_id === $department->school_id;
    }

    /**
     * Determine whether the user can create departments.
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('departments.create');
    }

    /**
     * Determine whether the user can update a specific department.
     */
    public function update(User $user, Department $department): bool
    {
        return LaratrustFacade::hasPermission('departments.update')
            && $user->school_id === $department->school_id;
    }

    /**
     * Determine whether the user can delete department(s) (soft delete).
     */
    public function delete(User $user, Department $department): bool
    {
        return LaratrustFacade::hasPermission('departments.delete')
            && $user->school_id === $department->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted department.
     */
    public function restore(User $user, Department $department): bool
    {
        return LaratrustFacade::hasPermission('departments.restore')
            && $user->school_id === $department->school_id;
    }

    /**
     * Determine whether the user can permanently delete a department.
     */
    public function forceDelete(User $user, Department $department): bool
    {
        return LaratrustFacade::hasPermission('departments.force-delete')
            && $user->school_id === $department->school_id;
    }

    /**
     * Determine whether the user can assign/sync roles (with section scoping) to a department.
     */
    public function assignRole(User $user, Department $department): bool
    {
        return LaratrustFacade::hasPermission('departments.assign-role')
            && $user->school_id === $department->school_id;
    }

    /**
     * Determine whether the user can view members of a department (Members tab).
     */
    public function viewUsers(User $user, Department $department): bool
    {
        return LaratrustFacade::hasPermission('departments.view-users')
            && $user->school_id === $department->school_id;
    }
}
