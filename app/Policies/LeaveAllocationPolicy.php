<?php

namespace App\Policies;

use App\Models\Employee\LeaveAllocation;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for LeaveAllocation model.
 */
class LeaveAllocationPolicy
{
    /**
     * Determine whether the user can view any leave allocations.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('leave-allocations.view');
    }

    /**
     * Determine whether the user can view a specific leave allocation.
     *
     * @param User $user
     * @param LeaveAllocation $leaveAllocation
     * @return bool
     */
    public function view(User $user, LeaveAllocation $leaveAllocation): bool
    {
        return LaratrustFacade::hasPermission('leave-allocations.view') && $user->school_id === $leaveAllocation->school_id;
    }

    /**
     * Determine whether the user can create leave allocations.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('leave-allocations.create');
    }

    /**
     * Determine whether the user can update a specific leave allocation.
     *
     * @param User $user
     * @param LeaveAllocation $leaveAllocation
     * @return bool
     */
    public function update(User $user, LeaveAllocation $leaveAllocation): bool
    {
        return LaratrustFacade::hasPermission('leave-allocations.update') && $user->school_id === $leaveAllocation->school_id;
    }

    /**
     * Determine whether the user can delete a specific leave allocation.
     *
     * @param User $user
     * @param LeaveAllocation $leaveAllocation
     * @return bool
     */
    public function delete(User $user, LeaveAllocation $leaveAllocation): bool
    {
        return LaratrustFacade::hasPermission('leave-allocations.delete') && $user->school_id === $leaveAllocation->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted leave allocation.
     *
     * @param User $user
     * @param LeaveAllocation $leaveAllocation
     * @return bool
     */
    public function restore(User $user, LeaveAllocation $leaveAllocation): bool
    {
        return LaratrustFacade::hasPermission('leave-allocations.restore') && $user->school_id === $leaveAllocation->school_id;
    }

    /**
     * Determine whether the user can permanently delete a leave allocation.
     *
     * @param User $user
     * @param LeaveAllocation $leaveAllocation
     * @return bool
     */
    public function forceDelete(User $user, LeaveAllocation $leaveAllocation): bool
    {
        return LaratrustFacade::hasPermission('leave-allocations.force-delete') && $user->school_id === $leaveAllocation->school_id;
    }
}