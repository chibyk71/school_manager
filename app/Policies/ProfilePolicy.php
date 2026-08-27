<?php

namespace App\Policies;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

/**
 * ProfilePolicy – Controls access to Profile model (central person entity)
 *
 * This is the single source of truth for profile-related permissions.
 * Since User and Profile are 1:1 (user_id on Profile), this policy also handles
 * login/account-related actions (reset password, create login, toggle status).
 *
 * Key Decisions:
 * - Regular users can view/edit their own profile
 * - Admins with permissions can view/edit any profile
 * - Force delete is explicitly allowed for admins (with safeguards)
 * - No direct create/store (profiles created via role services)
 * - Role-specific actions (e.g. edit student data) belong to role policies
 * - Merge action: admin-only for duplicate cleanup
 * - Security: self-ownership check + permission gates
 * - Multi-tenant safety: school context checked where relevant
 *
 * Fits into User Management Module:
 * - Used by ProfileController (index, show, edit, update, create-login, reset-password, destroy, merge)
 * - Called automatically by Laravel authorization (Gate::authorize('view', $profile))
 * - Integrates with Laratrust: uses can() for granular permissions
 * - No direct UI impact — purely backend authorization layer
 *
 * Permission Gates Used (should be defined in Gates.php or Laratrust):
 * - profile.view-any
 * - profile.view
 * - profile.edit-any
 * - profile.edit
 * - profile.delete-any
 * - profile.merge
 * - profile.force-delete
 * - profile.reset-password
 * - profile.create-login
 * - profile.toggle-status
 */

class ProfilePolicy
{
    use HandlesAuthorization;

    /**
     * View any profiles (list/index)
     */
    public function viewAny(User $user): bool
    {
        return $user->can('profile.view-any');
    }

    /**
     * View a specific profile
     * - Own profile: always allowed
     * - Any profile: admin with permission
     */
    public function view(User $user, Profile $profile): Response
    {
        // Self-view
        if ($profile->user_id === $user->id) {
            return $this->allow();
        }

        // Admin view-any
        if ($user->can('profile.view-any')) {
            return $this->allow();
        }

        return $this->deny('You do not have permission to view this profile.');
    }

    /**
     * Create profiles — intentionally forbidden
     * (profiles are created via role services: StudentEnrollmentService, etc.)
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Update a profile (personal data: name, DOB, phone, email, photo, etc.)
     * - Own profile: allowed
     * - Any profile: admin with permission
     */
    public function update(User $user, Profile $profile): Response
    {
        if ($profile->user_id === $user->id) {
            return $this->allow();
        }

        if ($user->can('profile.edit-any')) {
            return $this->allow();
        }

        return $this->deny('You do not have permission to edit this profile.');
    }

    /**
     * Soft-delete a profile
     * - Only admins with permission
     * - Triggers cascade soft-delete to roles/User (handled in model boot)
     */
    public function delete(User $user, Profile $profile): Response
    {
        if ($user->can('profile.delete-any')) {
            return $this->allow();
        }

        return $this->deny('You do not have permission to delete this profile.');
    }

    /**
     * Force-delete a profile (permanent removal)
     * - Extremely restricted: only super-admins
     * - Bypasses soft-delete cascade
     */
    public function forceDelete(User $user, Profile $profile): Response
    {
        if ($user->can('profile.force-delete')) {
            return $this->allow();
        }

        return $this->deny('You do not have permission to permanently delete this profile.');
    }

    /**
     * Restore a soft-deleted profile
     */
    public function restore(User $user, Profile $profile): Response
    {
        if ($user->can('profile.delete-any')) {
            return $this->allow();
        }

        return $this->deny('You do not have permission to restore this profile.');
    }

    /**
     * Merge this profile into another (admin duplicate cleanup)
     */
    public function merge(User $user, Profile $profile): bool
    {
        return $user->can('profile.merge');
    }

    /**
     * Create a login account for this profile
     * - Self: allowed if no existing login
     * - Admin: allowed for any
     */
    public function createLogin(User $user, Profile $profile): Response
    {
        // Self — only if no login exists yet
        if ($profile->user_id === $user->id && !$profile->user) {
            return $this->allow();
        }

        // Admin
        if ($user->can('profile.create-login')) {
            return $this->allow();
        }

        return $this->deny('You do not have permission to create a login for this profile.');
    }

    /**
     * Reset password for this profile's user
     * - Self: allowed (forgot password flow)
     * - Admin: allowed for any
     */
    public function resetPassword(User $user, Profile $profile): Response
    {
        // Self-reset (forgot password)
        if ($profile->user_id === $user->id) {
            return $this->allow();
        }

        // Admin reset
        if ($user->can('profile.reset-password')) {
            return $this->allow();
        }

        return $this->deny('You do not have permission to reset this profile\'s password.');
    }

    /**
     * Toggle active/inactive status
     * - Self: not allowed (security)
     * - Admin: allowed
     */
    public function toggleStatus(User $user, Profile $profile): Response
    {
        // Prevent self-toggle
        if ($profile->user_id === $user->id) {
            return $this->deny('You cannot change your own status.');
        }

        if ($user->can('profile.toggle-status')) {
            return $this->allow();
        }

        return $this->deny('You do not have permission to change this profile\'s status.');
    }

    /**
     * Upload/change avatar for this profile
     * - Self: allowed
     * - Admin: allowed for any
     */
    public function uploadAvatar(User $user, Profile $profile): Response
    {
        if ($profile->user_id === $user->id) {
            return $this->allow();
        }

        if ($user->can('profile.upload-avatar')) {
            return $this->allow();
        }

        return $this->deny('You do not have permission to change this profile\'s avatar.');
    }
}
