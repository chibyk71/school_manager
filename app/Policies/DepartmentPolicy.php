<?php

namespace App\Policies;

use App\Models\Employee\Department;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for Department model.
 */
class DepartmentPolicy
{
    /**
     * Determine whether the user can view any departments.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('departments.view');
    }

    /**
     * Determine whether the user can view a specific department.
     *
     * @param User $user
     * @param Department $department
     * @return bool
     */
    public function view(User $user, Department $department): bool
    {
        return LaratrustFacade::hasPermission('departments.view') && $user->school_id === $department->school_id;
    }

    /**
     * Determine whether the user can create departments.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('departments.create');
    }

    /**
     * Determine whether the user can update a specific department.
     *
     * @param User $user
     * @param Department $department
     * @return bool
     */
    public function update(User $user, Department $department): bool
    {
        return LaratrustFacade::hasPermission('departments.update') && $user->school_id === $department->school_id;
    }

    /**
     * Determine whether the user can delete a specific department.
     *
     * @param User $user
     * @param Department $department
     * @return bool
     */
    public function delete(User $user, Department $department): bool
    {
        return LaratrustFacade::hasPermission('departments.delete') && $user->school_id === $department->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted department.
     *
     * @param User $user
     * @param Department $department
     * @return bool
     */
    public function restore(User $user, Department $department): bool
    {
        return LaratrustFacade::hasPermission('departments.restore') && $user->school_id === $department->school_id;
    }

    /**
     * Determine whether the user can permanently delete a department.
     *
     * @param User $user
     * @param Department $department
     * @return bool
     */
    public function forceDelete(User $user, Department $department): bool
    {
        return LaratrustFacade::hasPermission('departments.force-delete') && $user->school_id === $department->school_id;
    }

    /**
     * Determine whether the user can assign roles to a department.
     *
     * @param User $user
     * @param Department $department
     * @return bool
     */
    public function assignRole(User $user, Department $department): bool
    {
        return LaratrustFacade::hasPermission('departments.assign-role') && $user->school_id === $department->school_id;
    }

    /**
     * Determine whether the user can view users in a department.
     *
     * @param User $user
     * @param Department $department
     * @return bool
     */
    public function viewUsers(User $user, Department $department): bool
    {
        return LaratrustFacade::hasPermission('departments.view-users') && $user->school_id === $department->school_id;
    }
}