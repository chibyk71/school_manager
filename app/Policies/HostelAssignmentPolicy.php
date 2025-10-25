<?php

namespace App\Policies;

use App\Models\Housing\HostelAssignment;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for HostelAssignment model.
 *
 * Defines permissions for CRUD operations on hostel assignments, scoped to the user's role and permissions
 * in a multi-tenant SaaS environment.
 */
class HostelAssignmentPolicy
{
    /**
     * Determine whether the user can view any hostel assignments.
     *
     * @param User $user The authenticated user.
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('hostel-assignment.view-any');
    }

    /**
     * Determine whether the user can view a specific hostel assignment.
     *
     * @param User $user The authenticated user.
     * @param HostelAssignment $hostelAssignment The hostel assignment instance.
     * @return bool
     */
    public function view(User $user, HostelAssignment $hostelAssignment): bool
    {
        return LaratrustFacade::hasPermission('hostel-assignment.view')
            && $user->school_id === $hostelAssignment->room->hostel->school_id;
    }

    /**
     * Determine whether the user can create hostel assignments.
     *
     * @param User $user The authenticated user.
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('hostel-assignment.create');
    }

    /**
     * Determine whether the user can update a specific hostel assignment.
     *
     * @param User $user The authenticated user.
     * @param HostelAssignment $hostelAssignment The hostel assignment instance.
     * @return bool
     */
    public function update(User $user, HostelAssignment $hostelAssignment): bool
    {
        return LaratrustFacade::hasPermission('hostel-assignment.update')
            && $user->school_id === $hostelAssignment->room->hostel->school_id;
    }

    /**
     * Determine whether the user can delete a specific hostel assignment.
     *
     * @param User $user The authenticated user.
     * @param HostelAssignment $hostelAssignment The hostel assignment instance.
     * @return bool
     */
    public function delete(User $user, HostelAssignment $hostelAssignment): bool
    {
        return LaratrustFacade::hasPermission('hostel-assignment.delete')
            && $user->school_id === $hostelAssignment->room->hostel->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted hostel assignment.
     *
     * @param User $user The authenticated user.
     * @param HostelAssignment $hostelAssignment The hostel assignment instance.
     * @return bool
     */
    public function restore(User $user, HostelAssignment $hostelAssignment): bool
    {
        return LaratrustFacade::hasPermission('hostel-assignment.restore')
            && $user->school_id === $hostelAssignment->room->hostel->school_id;
    }

    /**
     * Determine whether the user can permanently delete a hostel assignment.
     *
     * @param User $user The authenticated user.
     * @param HostelAssignment $hostelAssignment The hostel assignment instance.
     * @return bool
     */
    public function forceDelete(User $user, HostelAssignment $hostelAssignment): bool
    {
        return LaratrustFacade::hasPermission('hostel-assignment.force-delete')
            && $user->school_id === $hostelAssignment->room->hostel->school_id;
    }
}
