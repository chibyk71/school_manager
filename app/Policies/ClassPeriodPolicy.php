<?php

namespace App\Policies;

use App\Models\Academic\ClassPeriod;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for ClassPeriod model.
 */
class ClassPeriodPolicy
{
    /**
     * Determine whether the user can view any class periods.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('class-periods.view');
    }

    /**
     * Determine whether the user can view a specific class period.
     *
     * @param User $user
     * @param ClassPeriod $classPeriod
     * @return bool
     */
    public function view(User $user, ClassPeriod $classPeriod): bool
    {
        return LaratrustFacade::hasPermission('class-periods.view') && $user->school_id === $classPeriod->school_id;
    }

    /**
     * Determine whether the user can create class periods.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('class-periods.create');
    }

    /**
     * Determine whether the user can update a specific class period.
     *
     * @param User $user
     * @param ClassPeriod $classPeriod
     * @return bool
     */
    public function update(User $user, ClassPeriod $classPeriod): bool
    {
        return LaratrustFacade::hasPermission('class-periods.update') && $user->school_id === $classPeriod->school_id;
    }

    /**
     * Determine whether the user can delete a specific class period.
     *
     * @param User $user
     * @param ClassPeriod $classPeriod
     * @return bool
     */
    public function delete(User $user, ClassPeriod $classPeriod): bool
    {
        return LaratrustFacade::hasPermission('class-periods.delete') && $user->school_id === $classPeriod->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted class period.
     *
     * @param User $user
     * @param ClassPeriod $classPeriod
     * @return bool
     */
    public function restore(User $user, ClassPeriod $classPeriod): bool
    {
        return LaratrustFacade::hasPermission('class-periods.restore') && $user->school_id === $classPeriod->school_id;
    }

    /**
     * Determine whether the user can permanently delete a class period.
     *
     * @param User $user
     * @param ClassPeriod $classPeriod
     * @return bool
     */
    public function forceDelete(User $user, ClassPeriod $classPeriod): bool
    {
        return LaratrustFacade::hasPermission('class-periods.force-delete') && $user->school_id === $classPeriod->school_id;
    }
}