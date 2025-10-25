<?php

namespace App\Policies;

use App\Models\Misc\AttendanceSession;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendanceSessionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any attendance sessions.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance-sessions.view');
    }

    /**
     * Determine whether the user can view the attendance session.
     *
     * @param User $user
     * @param AttendanceSession $attendanceSession
     * @return bool
     */
    public function view(User $user, AttendanceSession $attendanceSession): bool
    {
        return $user->hasPermission('attendance-sessions.view') &&
               $attendanceSession->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can create attendance sessions.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('attendance-sessions.create');
    }

    /**
     * Determine whether the user can update the attendance session.
     *
     * @param User $user
     * @param AttendanceSession $attendanceSession
     * @return bool
     */
    public function update(User $user, AttendanceSession $attendanceSession): bool
    {
        return $user->hasPermission('attendance-sessions.update') &&
               $attendanceSession->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can delete the attendance session.
     *
     * @param User $user
     * @param AttendanceSession $attendanceSession
     * @return bool
     */
    public function delete(User $user, AttendanceSession $attendanceSession): bool
    {
        return $user->hasPermission('attendance-sessions.delete') &&
               $attendanceSession->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can restore the attendance session.
     *
     * @param User $user
     * @param AttendanceSession $attendanceSession
     * @return bool
     */
    public function restore(User $user, AttendanceSession $attendanceSession): bool
    {
        return $user->hasPermission('attendance-sessions.restore') &&
               $attendanceSession->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can permanently delete the attendance session.
     *
     * @param User $user
     * @param AttendanceSession $attendanceSession
     * @return bool
     */
    public function forceDelete(User $user, AttendanceSession $attendanceSession): bool
    {
        return $user->hasPermission('attendance-sessions.force-delete') &&
               $attendanceSession->school_id === GetSchoolModel()->id;
    }
}
