<?php

namespace App\Policies;

use App\Models\Employee\LeaveRequest;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for LeaveRequest model.
 */
class LeaveRequestPolicy
{
    /**
     * Determine whether the user can view any leave requests.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('leave-requests.view');
    }

    /**
     * Determine whether the user can view a specific leave request.
     *
     * @param User $user
     * @param LeaveRequest $leaveRequest
     * @return bool
     */
    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        return LaratrustFacade::hasPermission('leave-requests.view') && $user->school_id === $leaveRequest->school_id;
    }

    /**
     * Determine whether the user can create leave requests.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('leave-requests.create');
    }

    /**
     * Determine whether the user can update a specific leave request.
     *
     * @param User $user
     * @param LeaveRequest $leaveRequest
     * @return bool
     */
    public function update(User $user, LeaveRequest $leaveRequest): bool
    {
        return LaratrustFacade::hasPermission('leave-requests.update') && $user->school_id === $leaveRequest->school_id;
    }

    /**
     * Determine whether the user can delete a specific leave request.
     *
     * @param User $user
     * @param LeaveRequest $leaveRequest
     * @return bool
     */
    public function delete(User $user, LeaveRequest $leaveRequest): bool
    {
        return LaratrustFacade::hasPermission('leave-requests.delete') && $user->school_id === $leaveRequest->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted leave request.
     *
     * @param User $user
     * @param LeaveRequest $leaveRequest
     * @return bool
     */
    public function restore(User $user, LeaveRequest $leaveRequest): bool
    {
        return LaratrustFacade::hasPermission('leave-requests.restore') && $user->school_id === $leaveRequest->school_id;
    }

    /**
     * Determine whether the user can permanently delete a leave request.
     *
     * @param User $user
     * @param LeaveRequest $leaveRequest
     * @return bool
     */
    public function forceDelete(User $user, LeaveRequest $leaveRequest): bool
    {
        return LaratrustFacade::hasPermission('leave-requests.force-delete') && $user->school_id === $leaveRequest->school_id;
    }

    /**
     * Determine whether the user can approve or reject a leave request.
     *
     * @param User $user
     * @param LeaveRequest $leaveRequest
     * @return bool
     */
    public function manageApproval(User $user, LeaveRequest $leaveRequest): bool
    {
        return LaratrustFacade::hasPermission('leave-requests.manage-approval') && $user->school_id === $leaveRequest->school_id;
    }
}