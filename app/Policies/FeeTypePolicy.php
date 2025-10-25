<?php

namespace App\Policies;

use App\Models\Finance\FeeType;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for FeeType model.
 */
class FeeTypePolicy
{
    /**
     * Determine whether the user can view any fee types.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('fee-types.view');
    }

    /**
     * Determine whether the user can view the fee type.
     *
     * @param User $user
     * @param FeeType $feeType
     * @return bool
     */
    public function view(User $user, FeeType $feeType): bool
    {
        return LaratrustFacade::hasPermission('fee-types.view') && $user->school_id === $feeType->school_id;
    }

    /**
     * Determine whether the user can create fee types.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('fee-types.create');
    }

    /**
     * Determine whether the user can update the fee type.
     *
     * @param User $user
     * @param FeeType $feeType
     * @return bool
     */
    public function update(User $user, FeeType $feeType): bool
    {
        return LaratrustFacade::hasPermission('fee-types.update') && $user->school_id === $feeType->school_id;
    }

    /**
     * Determine whether the user can delete the fee type.
     *
     * @param User $user
     * @param FeeType $feeType
     * @return bool
     */
    public function delete(User $user, FeeType $feeType): bool
    {
        return LaratrustFacade::hasPermission('fee-types.delete') && $user->school_id === $feeType->school_id;
    }

    /**
     * Determine whether the user can restore the fee type.
     *
     * @param User $user
     * @param FeeType $feeType
     * @return bool
     */
    public function restore(User $user, FeeType $feeType): bool
    {
        return LaratrustFacade::hasPermission('fee-types.restore') && $user->school_id === $feeType->school_id;
    }

    /**
     * Determine whether the user can permanently delete the fee type.
     *
     * @param User $user
     * @param FeeType $feeType
     * @return bool
     */
    public function forceDelete(User $user, FeeType $feeType): bool
    {
        return LaratrustFacade::hasPermission('fee-types.force-delete') && $user->school_id === $feeType->school_id;
    }
}