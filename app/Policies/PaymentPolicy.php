<?php

namespace App\Policies;

use App\Models\Finance\Payment;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for Payment model.
 */
class PaymentPolicy
{
    /**
     * Determine whether the user can view any payments.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('payments.view');
    }

    /**
     * Determine whether the user can view the payment.
     *
     * @param User $user
     * @param Payment $payment
     * @return bool
     */
    public function view(User $user, Payment $payment): bool
    {
        return LaratrustFacade::hasPermission('payments.view') && $user->school_id === $payment->school_id;
    }

    /**
     * Determine whether the user can create payments.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('payments.create');
    }

    /**
     * Determine whether the user can update the payment.
     *
     * @param User $user
     * @param Payment $payment
     * @return bool
     */
    public function update(User $user, Payment $payment): bool
    {
        return LaratrustFacade::hasPermission('payments.update') && $user->school_id === $payment->school_id;
    }

    /**
     * Determine whether the user can delete the payment.
     *
     * @param User $user
     * @param Payment $payment
     * @return bool
     */
    public function delete(User $user, Payment $payment): bool
    {
        return LaratrustFacade::hasPermission('payments.delete') && $user->school_id === $payment->school_id;
    }

    /**
     * Determine whether the user can restore the payment.
     *
     * @param User $user
     * @param Payment $payment
     * @return bool
     */
    public function restore(User $user, Payment $payment): bool
    {
        return LaratrustFacade::hasPermission('payments.restore') && $user->school_id === $payment->school_id;
    }

    /**
     * Determine whether the user can permanently delete the payment.
     *
     * @param User $user
     * @param Payment $payment
     * @return bool
     */
    public function forceDelete(User $user, Payment $payment): bool
    {
        return LaratrustFacade::hasPermission('payments.force-delete') && $user->school_id === $payment->school_id;
    }
}