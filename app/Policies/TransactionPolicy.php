<?php

namespace App\Policies;

use App\Models\Finance\Transaction;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for Transaction model.
 */
class TransactionPolicy
{
    /**
     * Determine whether the user can view any transactions.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('transactions.view');
    }

    /**
     * Determine whether the user can view the transaction.
     *
     * @param User $user
     * @param Transaction $transaction
     * @return bool
     */
    public function view(User $user, Transaction $transaction): bool
    {
        return LaratrustFacade::hasPermission('transactions.view') && $user->school_id === $transaction->school_id;
    }

    /**
     * Determine whether the user can create transactions.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('transactions.create');
    }

    /**
     * Determine whether the user can update the transaction.
     *
     * @param User $user
     * @param Transaction $transaction
     * @return bool
     */
    public function update(User $user, Transaction $transaction): bool
    {
        return LaratrustFacade::hasPermission('transactions.update') && $user->school_id === $transaction->school_id;
    }

    /**
     * Determine whether the user can delete the transaction.
     *
     * @param User $user
     * @param Transaction $transaction
     * @return bool
     */
    public function delete(User $user, Transaction $transaction): bool
    {
        return LaratrustFacade::hasPermission('transactions.delete') && $user->school_id === $transaction->school_id;
    }

    /**
     * Determine whether the user can restore the transaction.
     *
     * @param User $user
     * @param Transaction $transaction
     * @return bool
     */
    public function restore(User $user, Transaction $transaction): bool
    {
        return LaratrustFacade::hasPermission('transactions.restore') && $user->school_id === $transaction->school_id;
    }

    /**
     * Determine whether the user can permanently delete the transaction.
     *
     * @param User $user
     * @param Transaction $transaction
     * @return bool
     */
    public function forceDelete(User $user, Transaction $transaction): bool
    {
        return LaratrustFacade::hasPermission('transactions.force-delete') && $user->school_id === $transaction->school_id;
    }
}