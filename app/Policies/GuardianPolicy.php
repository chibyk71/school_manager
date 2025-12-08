<?php

namespace App\Policies;

use App\Models\Guardian;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

/**
 * Authorization policy for Guardian model.
 *
 * Fully compatible with multi-role users and multi-school tenancy.
 */
class GuardianPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any guardians.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('guardian.view-any');
    }

    /**
     * Determine whether the user can view a specific guardian.
     */
    public function view(User $user, Guardian $guardian): Response
    {
        if (! $user->can('guardian.view')) {
            return $this->deny('You do not have permission to view guardians.');
        }

        $hasAccess = $user->profiles()
            ->where('school_id', $guardian->school_id)
            ->exists();

        return $hasAccess
            ? $this->allow()
            : $this->deny('You can only view guardians from your school.');
    }

    /**
     * Determine whether the user can create guardians.
     */
    public function create(User $user): bool
    {
        return $user->can('guardian.create');
    }

    /**
     * Determine whether the user can update a specific guardian.
     */
    public function update(User $user, Guardian $guardian): Response
    {
        if (! $user->can('guardian.update')) {
            return $this->deny('You do not have permission to update guardians.');
        }

        $hasAccess = $user->profiles()
            ->where('school_id', $guardian->school_id)
            ->exists();

        return $hasAccess
            ? $this->allow()
            : $this->deny('You can only update guardians from your school.');
    }

    /**
     * Determine whether the user can delete a specific guardian.
     */
    public function delete(User $user, Guardian $guardian): Response
    {
        if (! $user->can('guardian.delete')) {
            return $this->deny('You do not have permission to delete guardians.');
        }

        $hasAccess = $user->profiles()
            ->where('school_id', $guardian->school_id)
            ->exists();

        return $hasAccess
            ? $this->allow()
            : $this->deny('You can only delete guardians from your school.');
    }

    /**
     * Determine whether the user can restore a soft-deleted guardian.
     */
    public function restore(User $user, Guardian $guardian): Response
    {
        if (! $user->can('guardian.restore')) {
            return $this->deny('You do not have permission to restore guardians.');
        }

        $hasAccess = $user->profiles()
            ->where('school_id', $guardian->school_id)
            ->exists();

        return $hasAccess
            ? $this->allow()
            : $this->deny('You can only restore guardians from your school.');
    }

    /**
     * Determine whether the user can permanently delete a guardian.
     */
    public function forceDelete(User $user, Guardian $guardian): Response
    {
        if (! $user->can('guardian.force-delete')) {
            return $this->deny('You do not have permission to permanently delete guardians.');
        }

        $hasAccess = $user->profiles()
            ->where('school_id', $guardian->school_id)
            ->exists();

        return $hasAccess
            ? $this->allow()
            : $this->deny('You can only permanently delete guardians from your school.');
    }
}
