<?php

namespace App\Policies;

use App\Models\Configuration\LeaveType;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for LeaveType model.
 */
class LeaveTypePolicy
{
    /**
     * Determine whether the user can view any leave types.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('leave-types.view');
    }

    /**
     * Determine whether the user can view a specific leave type.
     *
     * @param User $user
     * @param LeaveType $leaveType
     * @return bool
     */
    public function view(User $user, LeaveType $leaveType): bool
    {
        return LaratrustFacade::hasPermission('leave-types.view') && $user->school_id === $leaveType->school_id;
    }

    /**
     * Determine whether the user can create leave types.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('leave-types.create');
    }

    /**
     * Determine whether the user can update a specific leave type.
     *
     * @param User $user
     * @param LeaveType $leaveType
     * @return bool
     */
    public function update(User $user, LeaveType $leaveType): bool
    {
        return LaratrustFacade::hasPermission('leave-types.update') && $user->school_id === $leaveType->school_id;
    }

    /**
     * Determine whether the user can delete a specific leave type.
     *
     * @param User $user
     * @param LeaveType $leaveType
     * @return bool
     */
    public function delete(User $user, LeaveType $leaveType): bool
    {
        return LaratrustFacade::hasPermission('leave-types.delete') && $user->school_id === $leaveType->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted leave type.
     *
     * @param User $user
     * @param LeaveType $leaveType
     * @return bool
     */
    public function restore(User $user, LeaveType $leaveType): bool
    {
        return LaratrustFacade::hasPermission('leave-types.restore') && $user->school_id === $leaveType->school_id;
    }

    /**
     * Determine whether the user can permanently delete a leave type.
     *
     * @param User $user
     * @param LeaveType $leaveType
     * @return bool
     */
    public function forceDelete(User $user, LeaveType $leaveType): bool
    {
        return LaratrustFacade::hasPermission('leave-types.force-delete') && $user->school_id === $leaveType->school_id;
    }
}