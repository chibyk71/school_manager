<?php

namespace App\Policies;

use App\Models\Employee\LeaveLedger;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for LeaveLedger model.
 */
class LeaveLedgerPolicy
{
    /**
     * Determine whether the user can view any leave ledger entries.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('leave-ledgers.view');
    }

    /**
     * Determine whether the user can view a specific leave ledger entry.
     *
     * @param User $user
     * @param LeaveLedger $leaveLedger
     * @return bool
     */
    public function view(User $user, LeaveLedger $leaveLedger): bool
    {
        return LaratrustFacade::hasPermission('leave-ledgers.view') && $user->school_id === $leaveLedger->school_id;
    }

    /**
     * Determine whether the user can create leave ledger entries.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('leave-ledgers.create');
    }

    /**
     * Determine whether the user can update a specific leave ledger entry.
     *
     * @param User $user
     * @param LeaveLedger $leaveLedger
     * @return bool
     */
    public function update(User $user, LeaveLedger $leaveLedger): bool
    {
        return LaratrustFacade::hasPermission('leave-ledgers.update') && $user->school_id === $leaveLedger->school_id;
    }

    /**
     * Determine whether the user can delete a specific leave ledger entry.
     *
     * @param User $user
     * @param LeaveLedger $leaveLedger
     * @return bool
     */
    public function delete(User $user, LeaveLedger $leaveLedger): bool
    {
        return LaratrustFacade::hasPermission('leave-ledgers.delete') && $user->school_id === $leaveLedger->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted leave ledger entry.
     *
     * @param User $user
     * @param LeaveLedger $leaveLedger
     * @return bool
     */
    public function restore(User $user, LeaveLedger $leaveLedger): bool
    {
        return LaratrustFacade::hasPermission('leave-ledgers.restore') && $user->school_id === $leaveLedger->school_id;
    }

    /**
     * Determine whether the user can permanently delete a leave ledger entry.
     *
     * @param User $user
     * @param LeaveLedger $leaveLedger
     * @return bool
     */
    public function forceDelete(User $user, LeaveLedger $leaveLedger): bool
    {
        return LaratrustFacade::hasPermission('leave-ledgers.force-delete') && $user->school_id === $leaveLedger->school_id;
    }
}