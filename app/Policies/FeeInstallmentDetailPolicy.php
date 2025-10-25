<?php

namespace App\Policies;

use App\Models\Finance\FeeInstallmentDetail;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for FeeInstallmentDetail model.
 */
class FeeInstallmentDetailPolicy
{
    /**
     * Determine whether the user can view any fee installment details.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('fee-installment-details.view');
    }

    /**
     * Determine whether the user can view the fee installment detail.
     *
     * @param User $user
     * @param FeeInstallmentDetail $feeInstallmentDetail
     * @return bool
     */
    public function view(User $user, FeeInstallmentDetail $feeInstallmentDetail): bool
    {
        return LaratrustFacade::hasPermission('fee-installment-details.view') && $user->school_id === $feeInstallmentDetail->school_id;
    }

    /**
     * Determine whether the user can create fee installment details.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('fee-installment-details.create');
    }

    /**
     * Determine whether the user can update the fee installment detail.
     *
     * @param User $user
     * @param FeeInstallmentDetail $feeInstallmentDetail
     * @return bool
     */
    public function update(User $user, FeeInstallmentDetail $feeInstallmentDetail): bool
    {
        return LaratrustFacade::hasPermission('fee-installment-details.update') && $user->school_id === $feeInstallmentDetail->school_id;
    }

    /**
     * Determine whether the user can delete the fee installment detail.
     *
     * @param User $user
     * @param FeeInstallmentDetail $feeInstallmentDetail
     * @return bool
     */
    public function delete(User $user, FeeInstallmentDetail $feeInstallmentDetail): bool
    {
        return LaratrustFacade::hasPermission('fee-installment-details.delete') && $user->school_id === $feeInstallmentDetail->school_id;
    }

    /**
     * Determine whether the user can restore the fee installment detail.
     *
     * @param User $user
     * @param FeeInstallmentDetail $feeInstallmentDetail
     * @return bool
     */
    public function restore(User $user, FeeInstallmentDetail $feeInstallmentDetail): bool
    {
        return LaratrustFacade::hasPermission('fee-installment-details.restore') && $user->school_id === $feeInstallmentDetail->school_id;
    }

    /**
     * Determine whether the user can permanently delete the fee installment detail.
     *
     * @param User $user
     * @param FeeInstallmentDetail $feeInstallmentDetail
     * @return bool
     */
    public function forceDelete(User $user, FeeInstallmentDetail $feeInstallmentDetail): bool
    {
        return LaratrustFacade::hasPermission('fee-installment-details.force-delete') && $user->school_id === $feeInstallmentDetail->school_id;
    }
}