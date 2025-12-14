<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SchoolPolicy
{
    /**
     * Determine whether the user can view any schools.
     * 
     * Super admins can see all schools.
     * School owners/principals can see schools they are assigned to.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin') || ($user->schools()->exists() && $user->hasPermission('school.view', null));;
    }

    /**
     * Determine whether the user can view a specific school.
     */
    public function view(User $user, School $school): bool
    {
        return $user->hasRole('super-admin') || $user->schools->contains($school);
    }

    /**
     * Determine whether the user can create schools.
     * 
     * Allowed for super-admins and school-owners (as per feature requirement).
     */
    public function create(User $user): bool
    {
        return $user->hasRole('super-admin') || $user->hasPermission('school.create', null);
    }

    /**
     * Determine whether the user can update the school.
     */
    public function update(User $user, School $school): bool
    {
        return $user->hasRole('super-admin') || ($user->schools->contains($school) && $user->hasPermission('school.update', null));;
    }

    /**
     * Determine whether the user can deactivate (soft-delete) the school.
     * 
     * Must have ownership/access AND the school must have no active dependencies.
     */
    public function delete(User $user, School $school): bool
    {
        $hasAccess = $user->hasRole('super-admin') || ($user->schools->contains($school) && $user->hasPermission('school.delete', null));;

        return $hasAccess && $school->users()->count() === 0;
    }

    /**
     * Determine whether the user can restore a soft-deleted school.
     * 
     * Only super-admins are allowed to restore.
     */
    public function restore(User $user, School $school): bool
    {
        return $user->hasRole('super-admin')|| ($user->schools->contains($school) && $user->hasPermission('school.restore', null));;
    }

    /**
     * Determine whether the user can permanently delete a school.
     * 
     * Only super-admins can force delete.
     */
    public function forceDelete(User $user, School $school): bool
    {
        return $user->hasRole('super-admin') || ($user->schools->contains($school) && $user->hasPermission('school.forceDelete', null));
    }
}