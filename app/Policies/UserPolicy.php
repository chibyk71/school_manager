<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view.users') || $user->can('view.staff') || $user->can('view.students');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): Response
    {
        // User can always view themselves
        if ($user->id === $model->id) {
            return Response::allow();
        }

        // Admins can view all users in their school
        if ($user->can('view.users')) {
            $school = GetSchoolModel();
            if ($model->schools()->where('school_id', $school->id)->exists()) {
                return Response::allow();
            }
        }

        // Role-specific viewing
        if ($model->isStaff() && $user->can('view.staff')) {
            return Response::allow();
        }

        if ($model->isStudent() && $user->can('view.students')) {
            return Response::allow();
        }

        if ($model->isGuardian() && $user->can('view.guardians')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to view this user.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create.users') ||
            $user->can('create.staff') ||
            $user->can('create.students') ||
            $user->can('create.guardians');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): Response
    {
        // User can always update themselves (profile data)
        if ($user->id === $model->id) {
            return Response::allow();
        }

        // Admins can update users in their school
        if ($user->can('edit.users')) {
            $school = GetSchoolModel();
            if ($model->schools()->where('school_id', $school->id)->exists()) {
                return Response::allow();
            }
        }

        // Role-specific editing
        if ($model->isStaff() && $user->can('edit.staff')) {
            return Response::allow();
        }

        if ($model->isStudent() && $user->can('edit.students')) {
            return Response::allow();
        }

        if ($model->isGuardian() && $user->can('edit.guardians')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to edit this user.');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): Response
    {
        // Users cannot delete themselves
        if ($user->id === $model->id) {
            return Response::deny('You cannot delete your own account.');
        }

        // Only super admins can delete users
        if ($user->can('delete.users')) {
            $school = GetSchoolModel();
            if ($model->schools()->where('school_id', $school->id)->exists()) {
                return Response::allow();
            }
        }

        return Response::deny('You do not have permission to delete users.');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->can('delete.users'); // Same permission as delete
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false; // Never allow force delete of users
    }

    /**
     * Determine whether the user can toggle the model's status.
     */
    public function toggleStatus(User $user, User $model): Response
    {
        // Users cannot toggle their own status
        if ($user->id === $model->id) {
            return Response::deny('You cannot change your own status.');
        }

        // Admins can toggle status for users in their school
        if ($user->can('edit.users')) {
            $school = GetSchoolModel();
            if ($model->schools()->where('school_id', $school->id)->exists()) {
                return Response::allow();
            }
        }

        // Role-specific status management
        if ($model->isStaff() && $user->can('edit.staff')) {
            return Response::allow();
        }

        if ($model->isStudent() && $user->can('edit.students')) {
            return Response::allow();
        }

        if ($model->isGuardian() && $user->can('edit.guardians')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to change this user\'s status.');
    }

    /**
     * Determine whether the user can reset the model's password.
     */
    public function resetPassword(User $user, User $model): Response
    {
        // Users can reset their own password
        if ($user->id === $model->id) {
            return Response::allow();
        }

        // Admins can reset passwords for users in their school
        if ($user->can('user.reset.passwords')) {
            $school = GetSchoolModel();
            if ($model->schools()->where('school_id', $school->id)->exists()) {
                return Response::allow();
            }
        }

        return Response::deny('You do not have permission to reset this user\'s password.');
    }
}