<?php

namespace App\Policies;

use App\Models\Finance\Fee;
use App\Models\User;
use Laratrust\LaratrustFacade;
use Illuminate\Auth\Access\Response;

/**
 * Authorization policy for Fee model.
 */
class FeePolicy
{
    /**
     * Determine whether the user can view any fees.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('view-fees');
    }

    /**
     * Determine whether the user can view the fee.
     *
     * @param User $user
     * @param Fee $fee
     * @return bool
     */
    public function view(User $user, Fee $fee): bool
    {
        return LaratrustFacade::hasPermission('view-fees') && $user->school_id === $fee->school_id;
    }

    /**
     * Determine whether the user can create fees.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('create-fees');
    }

    /**
     * Determine whether the user can update the fee.
     *
     * @param User $user
     * @param Fee $fee
     * @return bool
     */
    public function update(User $user, Fee $fee): bool
    {
        return LaratrustFacade::hasPermission('edit-fees') && $user->school_id === $fee->school_id;
    }

    /**
     * Determine whether the user can delete the fee.
     *
     * @param User $user
     * @param Fee $fee
     * @return bool
     */
    public function delete(User $user, Fee $fee): bool
    {
        return LaratrustFacade::hasPermission('delete-fees') && $user->school_id === $fee->school_id;
    }

    /**
     * Determine whether the user can restore the fee.
     *
     * @param User $user
     * @param Fee $fee
     * @return bool
     */
    public function restore(User $user, Fee $fee): bool
    {
        return LaratrustFacade::hasPermission('restore-fees') && $user->school_id === $fee->school_id;
    }

    /**
     * Determine whether the user can permanently delete the fee.
     *
     * @param User $user
     * @param Fee $fee
     * @return bool
     */
    public function forceDelete(User $user, Fee $fee): bool
    {
        return LaratrustFacade::hasPermission('delete-fees') && $user->school_id === $fee->school_id;
    }
}