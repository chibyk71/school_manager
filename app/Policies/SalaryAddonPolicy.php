<?php

namespace App\Policies;

use App\Models\Employee\SalaryAddon;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for SalaryAddon model.
 */
class SalaryAddonPolicy
{
    /**
     * Determine whether the user can view any salary addons.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('salary-addons.view');
    }

    /**
     * Determine whether the user can view a specific salary addon.
     *
     * @param User $user
     * @param SalaryAddon $salaryAddon
     * @return bool
     */
    public function view(User $user, SalaryAddon $salaryAddon): bool
    {
        return LaratrustFacade::hasPermission('salary-addons.view') && $user->school_id === $salaryAddon->school_id;
    }

    /**
     * Determine whether the user can create salary addons.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('salary-addons.create');
    }

    /**
     * Determine whether the user can update a specific salary addon.
     *
     * @param User $user
     * @param SalaryAddon $salaryAddon
     * @return bool
     */
    public function update(User $user, SalaryAddon $salaryAddon): bool
    {
        return LaratrustFacade::hasPermission('salary-addons.update') && $user->school_id === $salaryAddon->school_id;
    }

    /**
     * Determine whether the user can delete a specific salary addon.
     *
     * @param User $user
     * @param SalaryAddon $salaryAddon
     * @return bool
     */
    public function delete(User $user, SalaryAddon $salaryAddon): bool
    {
        return LaratrustFacade::hasPermission('salary-addons.delete') && $user->school_id === $salaryAddon->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted salary addon.
     *
     * @param User $user
     * @param SalaryAddon $salaryAddon
     * @return bool
     */
    public function restore(User $user, SalaryAddon $salaryAddon): bool
    {
        return LaratrustFacade::hasPermission('salary-addons.restore') && $user->school_id === $salaryAddon->school_id;
    }

    /**
     * Determine whether the user can permanently delete a salary addon.
     *
     * @param User $user
     * @param SalaryAddon $salaryAddon
     * @return bool
     */
    public function forceDelete(User $user, SalaryAddon $salaryAddon): bool
    {
        return LaratrustFacade::hasPermission('salary-addons.force-delete') && $user->school_id === $salaryAddon->school_id;
    }
}