<?php

namespace App\Policies;

use App\Models\Academic\AcademicSession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Authorization policy for AcademicSession model.
 *
 * Defines permissions for viewing, creating, updating, deleting, restoring,
 * and force-deleting academic sessions using Laratrust permissions.
 *
 * @package App\Policies
 */
class AcademicSessionPolicy
{
    /**
     * Determine whether the user can view any academic sessions.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view-academic-sessions');
    }

    /**
     * Determine whether the user can view a specific academic session.
     *
     * @param User $user
     * @param AcademicSession $academicSession
     * @return bool
     */
    public function view(User $user, AcademicSession $academicSession): bool
    {
        return $user->hasPermission('view-academic-sessions')
            && $academicSession->school_id === GetSchoolModel()?->id;
    }

    /**
     * Determine whether the user can create academic sessions.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create-academic-sessions');
    }

    /**
     * Determine whether the user can update a specific academic session.
     *
     * @param User $user
     * @param AcademicSession $academicSession
     * @return bool
     */
    public function update(User $user, AcademicSession $academicSession): bool
    {
        return $user->hasPermission('update-academic-sessions')
            && $academicSession->school_id === GetSchoolModel()?->id;
    }

    /**
     * Determine whether the user can delete a specific academic session.
     *
     * @param User $user
     * @param AcademicSession $academicSession
     * @return bool
     */
    public function delete(User $user, AcademicSession $academicSession): bool
    {
        return $user->hasPermission('delete-academic-sessions')
            && $academicSession->school_id === GetSchoolModel()?->id;
    }

    /**
     * Determine whether the user can restore a soft-deleted academic session.
     *
     * @param User $user
     * @param AcademicSession $academicSession
     * @return bool
     */
    public function restore(User $user, AcademicSession $academicSession): bool
    {
        return $user->hasPermission('restore-academic-sessions')
            && $academicSession->school_id === GetSchoolModel()?->id;
    }

    /**
     * Determine whether the user can permanently delete an academic session.
     *
     * @param User $user
     * @param AcademicSession $academicSession
     * @return bool
     */
    public function forceDelete(User $user, AcademicSession $academicSession): bool
    {
        return $user->hasPermission('force-delete-academic-sessions')
            && $academicSession->school_id === GetSchoolModel()?->id;
    }
}