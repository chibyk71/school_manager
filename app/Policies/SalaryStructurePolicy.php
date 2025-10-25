<?php

namespace App\Policies;

use App\Models\Employee\SalaryStructure;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for SalaryStructure model.
 */
class SalaryStructurePolicy
{
    /**
     * Determine whether the user can view any salary structures.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('salary-structures.view');
    }

    /**
     * Determine whether the user can view a specific salary structure.
     *
     * @param User $user
     * @param SalaryStructure $salaryStructure
     * @return bool
     */
    public function view(User $user, SalaryStructure $salaryStructure): bool
    {
        return LaratrustFacade::hasPermission('salary-structures.view') && $user->school_id === $salaryStructure->school_id;
    }

    /**
     * Determine whether the user can create salary structures.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('salary-structures.create');
    }

    /**
     * Determine whether the user can update a specific salary structure.
     *
     * @param User $user
     * @param SalaryStructure $salaryStructure
     * @return bool
     */
    public function update(User $user, SalaryStructure $salaryStructure): bool
    {
        return LaratrustFacade::hasPermission('salary-structures.update') && $user->school_id === $salaryStructure->school_id;
    }

    /**
     * Determine whether the user can delete a specific salary structure.
     *
     * @param User $user
     * @param SalaryStructure $salaryStructure
     * @return bool
     */
    public function delete(User $user, SalaryStructure $salaryStructure): bool
    {
        return LaratrustFacade::hasPermission('salary-structures.delete') && $user->school_id === $salaryStructure->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted salary structure.
     *
     * @param User $user
     * @param SalaryStructure $salaryStructure
     * @return bool
     */
    public function restore(User $user, SalaryStructure $salaryStructure): bool
    {
        return LaratrustFacade::hasPermission('salary-structures.restore') && $user->school_id === $salaryStructure->school_id;
    }

    /**
     * Determine whether the user can permanently delete a salary structure.
     *
     * @param User $user
     * @param SalaryStructure $salaryStructure
     * @return bool
     */
    public function forceDelete(User $user, SalaryStructure $salaryStructure): bool
    {
        return LaratrustFacade::hasPermission('salary-structures.force-delete') && $user->school_id === $salaryStructure->school_id;
    }
}