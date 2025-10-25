<?php

namespace App\Policies;

use App\Models\Employee\DepartmentRole;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for DepartmentRole model.
 */
class DepartmentRolePolicy
{
    /**
     * Determine whether the user can view any department roles.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('department-roles.view');
    }

    /**
     * Determine whether the user can view a specific department role.
     *
     * @param User $user
     * @param DepartmentRole $departmentRole
     * @return bool
     */
    public function view(User $user, DepartmentRole $departmentRole): bool
    {
        return LaratrustFacade::hasPermission('department-roles.view') && $user->school_id === $departmentRole->school_id;
    }

    /**
     * Determine whether the user can create department roles.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('department-roles.create');
    }

    /**
     * Determine whether the user can update a specific department role.
     *
     * @param User $user
     * @param DepartmentRole $departmentRole
     * @return bool
     */
    public function update(User $user, DepartmentRole $departmentRole): bool
    {
        return LaratrustFacade::hasPermission('department-roles.update') && $user->school_id === $departmentRole->school_id;
    }

    /**
     * Determine whether the user can delete a specific department role.
     *
     * @param User $user
     * @param DepartmentRole $departmentRole
     * @return bool
     */
    public function delete(User $user, DepartmentRole $departmentRole): bool
    {
        return LaratrustFacade::hasPermission('department-roles.delete') && $user->school_id === $departmentRole->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted department role.
     *
     * @param User $user
     * @param DepartmentRole $departmentRole
     * @return bool
     */
    public function restore(User $user, DepartmentRole $departmentRole): bool
    {
        return LaratrustFacade::hasPermission('department-roles.restore') && $user->school_id === $departmentRole->school_id;
    }

    /**
     * Determine whether the user can permanently delete a department role.
     *
     * @param User $user
     * @param DepartmentRole $departmentRole
     * @return bool
     */
    public function forceDelete(User $user, DepartmentRole $departmentRole): bool
    {
        return LaratrustFacade::hasPermission('department-roles.force-delete') && $user->school_id === $departmentRole->school_id;
    }
}