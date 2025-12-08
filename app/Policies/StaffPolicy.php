<?php

namespace App\Policies;

use App\Models\Employee\Staff;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Authorization policy for Staff model.
 *
 * Uses proper school scoping via Profile relationship.
 * Leverages Laratrust's $user->can() syntax (cleaner than Facade).
 */
class StaffPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any staff.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('staff.view-any');
    }

    /**
     * Determine whether the user can view a specific staff member.
     *
     * Staff belongs to a school → check if the acting user has a profile in the same school.
     */
    public function view(User $user, Staff $staff): bool
    {
        // Must have view permission
        if (! $user->can('staff.view')) {
            return false;
        }

        // Must belong to the same school as the staff member
        return $user->profiles()
            ->where('school_id', $staff->school_id)
            ->exists();
    }

    /**
     * Determine whether the user can create staff.
     */
    public function create(User $user): bool
    {
        return $user->can('staff.create');
    }

    /**
     * Determine whether the user can update a specific staff member.
     */
    public function update(User $user, Staff $staff): bool
    {
        if (! $user->can('staff.update')) {
            return false;
        }

        return $user->profiles()
            ->where('school_id', $staff->school_id)
            ->exists();
    }

    /**
     * Determine whether the user can delete a specific staff member.
     */
    public function delete(User $user, Staff $staff): bool
    {
        if (! $user->can('staff.delete')) {
            return false;
        }

        return $user->profiles()
            ->where('school_id', $staff->school_id)
            ->exists();
    }

    /**
     * Determine whether the user can restore a soft-deleted staff member.
     */
    public function restore(User $user, Staff $staff): bool
    {
        if (! $user->can('staff.restore')) {
            return false;
        }

        return $user->profiles()
            ->where('school_id', $staff->school_id)
            ->exists();
    }

    /**
     * Determine whether the user can permanently delete a staff member.
     */
    public function forceDelete(User $user, Staff $staff): bool
    {
        if (! $user->can('staff.force-delete')) {
            return false;
        }

        return $user->profiles()
            ->where('school_id', $staff->school_id)
            ->exists();
    }
}
