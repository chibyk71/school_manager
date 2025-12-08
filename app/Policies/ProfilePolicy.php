<?php

namespace App\Policies;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

/**
 * ProfilePolicy
 *
 * Controls access to Profile model.
 * - Regular users can edit their own profiles
 * - Admins with specific permissions can edit/view any profile
 * - Supports profile merging (admin only)
 */
class ProfilePolicy
{
    use HandlesAuthorization;

    /**
     * Allow users to view any profile (used for admin profile list)
     */
    public function viewAny(User $user): bool
    {
        return $user->can('profile.view-any');
    }

    /**
     * Allow viewing of a specific profile
     * - User can view their own profiles
     * - Admins with permission can view any profile
     */
    public function view(User $user, Profile $profile): Response
    {
        $isOwner = $profile->user_id === $user->id;
        $isAdmin = $user->can('profile.view-any');

        return ($isOwner || $isAdmin)
            ? $this->allow()
            : $this->deny('You are not allowed to view this profile.');
    }

    /**
     * Users cannot create profiles directly (created via UserService)
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Update a profile
     * - Regular users can update their own profiles
     * - Admins with 'profile.edit-any' can update any profile
     */
    public function update(User $user, Profile $profile): Response
    {
        $isOwner = $profile->user_id === $user->id;
        $isAdmin = $user->can('profile.edit-any');

        if (! ($isOwner || $isAdmin)) {
            return $this->deny('You are not allowed to edit this profile.');
        }

        return $this->allow();
    }

    /**
     * Profiles should not be deleted directly (soft-deleted via role model)
     */
    public function delete(User $user, Profile $profile): bool
    {
        return false;
    }

    /**
     * Restore not allowed — profiles are managed through role models
     */
    public function restore(User $user, Profile $profile): bool
    {
        return false;
    }

    /**
     * Force delete not allowed
     */
    public function forceDelete(User $user, Profile $profile): bool
    {
        return false;
    }

    /**
     * Custom ability: Allow merging duplicate user profiles
     * Used in ProfileController@merge
     */
    public function merge(User $user): bool
    {
        return $user->can('profile.merge');
    }

    /**
     * Custom ability: Allow admins to upload avatar for any profile
     */
    public function uploadAvatar(User $user, Profile $profile): Response
    {
        $isOwner = $profile->user_id === $user->id;
        $isAdmin = $user->can('profile.edit-any');

        return ($isOwner || $isAdmin)
            ? $this->allow()
            : $this->deny('You are not allowed to change this avatar.');
    }
}
