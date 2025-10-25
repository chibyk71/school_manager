<?php

namespace App\Policies;

use App\Models\Employee\Staff;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for Staff model.
 */
class StaffPolicy
{
    /**
     * Determine whether the user can view any staff.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('staff.view');
    }

    /**
     * Determine whether the user can view a specific staff.
     *
     * @param User $user
     * @param Staff $staff
     * @return bool
     */
    public function view(User $user, Staff $staff): bool
    {
        return LaratrustFacade::hasPermission('staff.view') && $user->school_id === $staff->school_id;
    }

    /**
     * Determine whether the user can create staff.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('staff.create');
    }

    /**
     * Determine whether the user can update a specific staff.
     *
     * @param User $user
     * @param Staff $staff
     * @return bool
     */
    public function update(User $user, Staff $staff): bool
    {
        return LaratrustFacade::hasPermission('staff.update') && $user->school_id === $staff->school_id;
    }

    /**
     * Determine whether the user can delete a specific staff.
     *
     * @param User $user
     * @param Staff $staff
     * @return bool
     */
    public function delete(User $user, Staff $staff): bool
    {
        return LaratrustFacade::hasPermission('staff.delete') && $user->school_id === $staff->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted staff.
     *
     * @param User $user
     * @param Staff $staff
     * @return bool
     */
    public function restore(User $user, Staff $staff): bool
    {
        return LaratrustFacade::hasPermission('staff.restore') && $user->school_id === $staff->school_id;
    }

    /**
     * Determine whether the user can permanently delete a staff.
     *
     * @param User $user
     * @param Staff $staff
     * @return bool
     */
    public function forceDelete(User $user, Staff $staff): bool
    {
        return LaratrustFacade::hasPermission('staff.force-delete') && $user->school_id === $staff->school_id;
    }
}