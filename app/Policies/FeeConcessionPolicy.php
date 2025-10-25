<?php

namespace App\Policies;

use App\Models\Finance\FeeConcession;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for FeeConcession model.
 */
class FeeConcessionPolicy
{
    /**
     * Determine whether the user can view any fee concessions.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('fee-concessions.view');
    }

    /**
     * Determine whether the user can view the fee concession.
     *
     * @param User $user
     * @param FeeConcession $feeConcession
     * @return bool
     */
    public function view(User $user, FeeConcession $feeConcession): bool
    {
        return LaratrustFacade::hasPermission('fee-concessions.view') && $user->school_id === $feeConcession->school_id;
    }

    /**
     * Determine whether the user can create fee concessions.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('fee-concessions.create');
    }

    /**
     * Determine whether the user can update the fee concession.
     *
     * @param User $user
     * @param FeeConcession $feeConcession
     * @return bool
     */
    public function update(User $user, FeeConcession $feeConcession): bool
    {
        return LaratrustFacade::hasPermission('fee-concessions.update') && $user->school_id === $feeConcession->school_id;
    }

    /**
     * Determine whether the user can delete the fee concession.
     *
     * @param User $user
     * @param FeeConcession $feeConcession
     * @return bool
     */
    public function delete(User $user, FeeConcession $feeConcession): bool
    {
        return LaratrustFacade::hasPermission('fee-concessions.delete') && $user->school_id === $feeConcession->school_id;
    }

    /**
     * Determine whether the user can restore the fee concession.
     *
     * @param User $user
     * @param FeeConcession $feeConcession
     * @return bool
     */
    public function restore(User $user, FeeConcession $feeConcession): bool
    {
        return LaratrustFacade::hasPermission('fee-concessions.restore') && $user->school_id === $feeConcession->school_id;
    }

    /**
     * Determine whether the user can permanently delete the fee concession.
     *
     * @param User $user
     * @param FeeConcession $feeConcession
     * @return bool
     */
    public function forceDelete(User $user, FeeConcession $feeConcession): bool
    {
        return LaratrustFacade::hasPermission('fee-concessions.force-delete') && $user->school_id === $feeConcession->school_id;
    }
}