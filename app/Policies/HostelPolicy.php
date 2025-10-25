<?php

namespace App\Policies;

use App\Models\Housing\Hostel;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for Hostel model.
 *
 * Defines permissions for CRUD operations on hostels, scoped to the user's role and permissions
 * in a multi-tenant SaaS environment.
 */
class HostelPolicy
{
    /**
     * Determine whether the user can view any hostels.
     *
     * @param User $user The authenticated user.
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('hostel.view-any');
    }

    /**
     * Determine whether the user can view a specific hostel.
     *
     * @param User $user The authenticated user.
     * @param Hostel $hostel The hostel instance.
     * @return bool
     */
    public function view(User $user, Hostel $hostel): bool
    {
        return LaratrustFacade::hasPermission('hostel.view') && $user->school_id === $hostel->school_id;
    }

    /**
     * Determine whether the user can create hostels.
     *
     * @param User $user The authenticated user.
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('hostel.create');
    }

    /**
     * Determine whether the user can update a specific hostel.
     *
     * @param User $user The authenticated user.
     * @param Hostel $hostel The hostel instance.
     * @return bool
     */
    public function update(User $user, Hostel $hostel): bool
    {
        return LaratrustFacade::hasPermission('hostel.update') && $user->school_id === $hostel->school_id;
    }

    /**
     * Determine whether the user can delete a specific hostel.
     *
     * @param User $user The authenticated user.
     * @param Hostel $hostel The hostel instance.
     * @return bool
     */
    public function delete(User $user, Hostel $hostel): bool
    {
        return LaratrustFacade::hasPermission('hostel.delete') && $user->school_id === $hostel->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted hostel.
     *
     * @param User $user The authenticated user.
     * @param Hostel $hostel The hostel instance.
     * @return bool
     */
    public function restore(User $user, Hostel $hostel): bool
    {
        return LaratrustFacade::hasPermission('hostel.restore') && $user->school_id === $hostel->school_id;
    }

    /**
     * Determine whether the user can permanently delete a hostel.
     *
     * @param User $user The authenticated user.
     * @param Hostel $hostel The hostel instance.
     * @return bool
     */
    public function forceDelete(User $user, Hostel $hostel): bool
    {
        return LaratrustFacade::hasPermission('hostel.force-delete') && $user->school_id === $hostel->school_id;
    }
}
