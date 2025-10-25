<?php

namespace App\Policies;

use App\Models\Housing\HostelRoom;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for HostelRoom model.
 *
 * Defines permissions for CRUD operations on hostel rooms, scoped to the user's role and permissions
 * in a multi-tenant SaaS environment.
 */
class HostelRoomPolicy
{
    /**
     * Determine whether the user can view any hostel rooms.
     *
     * @param User $user The authenticated user.
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('hostel-room.view-any');
    }

    /**
     * Determine whether the user can view a specific hostel room.
     *
     * @param User $user The authenticated user.
     * @param HostelRoom $hostelRoom The hostel room instance.
     * @return bool
     */
    public function view(User $user, HostelRoom $hostelRoom): bool
    {
        return LaratrustFacade::hasPermission('hostel-room.view') && $user->school_id === $hostelRoom->hostel->school_id;
    }

    /**
     * Determine whether the user can create hostel rooms.
     *
     * @param User $user The authenticated user.
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('hostel-room.create');
    }

    /**
     * Determine whether the user can update a specific hostel room.
     *
     * @param User $user The authenticated user.
     * @param HostelRoom $hostelRoom The hostel room instance.
     * @return bool
     */
    public function update(User $user, HostelRoom $hostelRoom): bool
    {
        return LaratrustFacade::hasPermission('hostel-room.update') && $user->school_id === $hostelRoom->hostel->school_id;
    }

    /**
     * Determine whether the user can delete a specific hostel room.
     *
     * @param User $user The authenticated user.
     * @param HostelRoom $hostelRoom The hostel room instance.
     * @return bool
     */
    public function delete(User $user, HostelRoom $hostelRoom): bool
    {
        return LaratrustFacade::hasPermission('hostel-room.delete') && $user->school_id === $hostelRoom->hostel->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted hostel room.
     *
     * @param User $user The authenticated user.
     * @param HostelRoom $hostelRoom The hostel room instance.
     * @return bool
     */
    public function restore(User $user, HostelRoom $hostelRoom): bool
    {
        return LaratrustFacade::hasPermission('hostel-room.restore') && $user->school_id === $hostelRoom->hostel->school_id;
    }

    /**
     * Determine whether the user can permanently delete a hostel room.
     *
     * @param User $user The authenticated user.
     * @param HostelRoom $hostelRoom The hostel room instance.
     * @return bool
     */
    public function forceDelete(User $user, HostelRoom $hostelRoom): bool
    {
        return LaratrustFacade::hasPermission('hostel-room.force-delete') && $user->school_id === $hostelRoom->hostel->school_id;
    }
}
