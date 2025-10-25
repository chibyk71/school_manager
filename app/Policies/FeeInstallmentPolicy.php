<?php

namespace App\Policies;

use App\Models\Finance\FeeInstallment;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for FeeInstallment model.
 */
class FeeInstallmentPolicy
{
    /**
     * Determine whether the user can view any fee installments.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('fee-installments.view');
    }

    /**
     * Determine whether the user can view the fee installment.
     *
     * @param User $user
     * @param FeeInstallment $feeInstallment
     * @return bool
     */
    public function view(User $user, FeeInstallment $feeInstallment): bool
    {
        return LaratrustFacade::hasPermission('fee-installments.view') && $user->school_id === $feeInstallment->school_id;
    }

    /**
     * Determine whether the user can create fee installments.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('fee-installments.create');
    }

    /**
     * Determine whether the user can update the fee installment.
     *
     * @param User $user
     * @param FeeInstallment $feeInstallment
     * @return bool
     */
    public function update(User $user, FeeInstallment $feeInstallment): bool
    {
        return LaratrustFacade::hasPermission('fee-installments.update') && $user->school_id === $feeInstallment->school_id;
    }

    /**
     * Determine whether the user can delete the fee installment.
     *
     * @param User $user
     * @param FeeInstallment $feeInstallment
     * @return bool
     */
    public function delete(User $user, FeeInstallment $feeInstallment): bool
    {
        return LaratrustFacade::hasPermission('fee-installments.delete') && $user->school_id === $feeInstallment->school_id;
    }

    /**
     * Determine whether the user can restore the fee installment.
     *
     * @param User $user
     * @param FeeInstallment $feeInstallment
     * @return bool
     */
    public function restore(User $user, FeeInstallment $feeInstallment): bool
    {
        return LaratrustFacade::hasPermission('fee-installments.restore') && $user->school_id === $feeInstallment->school_id;
    }

    /**
     * Determine whether the user can permanently delete the fee installment.
     *
     * @param User $user
     * @param FeeInstallment $feeInstallment
     * @return bool
     */
    public function forceDelete(User $user, FeeInstallment $feeInstallment): bool
    {
        return LaratrustFacade::hasPermission('fee-installments.force-delete') && $user->school_id === $feeInstallment->school_id;
    }
}