<?php

namespace App\Policies;

use App\Models\Misc\AttendanceLedger;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendanceLedgerPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any attendance ledgers.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance-ledgers.view');
    }

    /**
     * Determine whether the user can view the attendance ledger.
     *
     * @param User $user
     * @param AttendanceLedger $attendanceLedger
     * @return bool
     */
    public function view(User $user, AttendanceLedger $attendanceLedger): bool
    {
        return $user->hasPermission('attendance-ledgers.view') &&
               $attendanceLedger->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can create attendance ledgers.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('attendance-ledgers.create');
    }

    /**
     * Determine whether the user can update the attendance ledger.
     *
     * @param User $user
     * @param AttendanceLedger $attendanceLedger
     * @return bool
     */
    public function update(User $user, AttendanceLedger $attendanceLedger): bool
    {
        return $user->hasPermission('attendance-ledgers.update') &&
               $attendanceLedger->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can delete the attendance ledger.
     *
     * @param User $user
     * @param AttendanceLedger $attendanceLedger
     * @return bool
     */
    public function delete(User $user, AttendanceLedger $attendanceLedger): bool
    {
        return $user->hasPermission('attendance-ledgers.delete') &&
               $attendanceLedger->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can restore the attendance ledger.
     *
     * @param User $user
     * @param AttendanceLedger $attendanceLedger
     * @return bool
     */
    public function restore(User $user, AttendanceLedger $attendanceLedger): bool
    {
        return $user->hasPermission('attendance-ledgers.restore') &&
               $attendanceLedger->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can permanently delete the attendance ledger.
     *
     * @param User $user
     * @param AttendanceLedger $attendanceLedger
     * @return bool
     */
    public function forceDelete(User $user, AttendanceLedger $attendanceLedger): bool
    {
        return $user->hasPermission('attendance-ledgers.force-delete') &&
               $attendanceLedger->school_id === GetSchoolModel()->id;
    }
}
