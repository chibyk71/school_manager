<?php

namespace App\Policies;

use App\Models\Employee\Salary;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for Salary model.
 */
class SalaryPolicy
{
    /**
     * Determine whether the user can view any salaries.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('salaries.view');
    }

    /**
     * Determine whether the user can view a specific salary.
     *
     * @param User $user
     * @param Salary $salary
     * @return bool
     */
    public function view(User $user, Salary $salary): bool
    {
        return LaratrustFacade::hasPermission('salaries.view') && $user->school_id === $salary->school_id;
    }

    /**
     * Determine whether the user can create salaries.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('salaries.create');
    }

    /**
     * Determine whether the user can update a specific salary.
     *
     * @param User $user
     * @param Salary $salary
     * @return bool
     */
    public function update(User $user, Salary $salary): bool
    {
        return LaratrustFacade::hasPermission('salaries.update') && $user->school_id === $salary->school_id;
    }

    /**
     * Determine whether the user can delete a specific salary.
     *
     * @param User $user
     * @param Salary $salary
     * @return bool
     */
    public function delete(User $user, Salary $salary): bool
    {
        return LaratrustFacade::hasPermission('salaries.delete') && $user->school_id === $salary->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted salary.
     *
     * @param User $user
     * @param Salary $salary
     * @return bool
     */
    public function restore(User $user, Salary $salary): bool
    {
        return LaratrustFacade::hasPermission('salaries.restore') && $user->school_id === $salary->school_id;
    }

    /**
     * Determine whether the user can permanently delete a salary.
     *
     * @param User $user
     * @param Salary $salary
     * @return bool
     */
    public function forceDelete(User $user, Salary $salary): bool
    {
        return LaratrustFacade::hasPermission('salaries.force-delete') && $user->school_id === $salary->school_id;
    }
}