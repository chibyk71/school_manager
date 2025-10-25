<?php

namespace App\Policies;

use App\Models\Employee\Payroll;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for Payroll model.
 */
class PayrollPolicy
{
    /**
     * Determine whether the user can view any payrolls.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('payrolls.view');
    }

    /**
     * Determine whether the user can view a specific payroll.
     *
     * @param User $user
     * @param Payroll $payroll
     * @return bool
     */
    public function view(User $user, Payroll $payroll): bool
    {
        return LaratrustFacade::hasPermission('payrolls.view') && $user->school_id === $payroll->school_id;
    }

    /**
     * Determine whether the user can create payrolls.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('payrolls.create');
    }

    /**
     * Determine whether the user can update a specific payroll.
     *
     * @param User $user
     * @param Payroll $payroll
     * @return bool
     */
    public function update(User $user, Payroll $payroll): bool
    {
        return LaratrustFacade::hasPermission('payrolls.update') && $user->school_id === $payroll->school_id;
    }

    /**
     * Determine whether the user can delete a specific payroll.
     *
     * @param User $user
     * @param Payroll $payroll
     * @return bool
     */
    public function delete(User $user, Payroll $payroll): bool
    {
        return LaratrustFacade::hasPermission('payrolls.delete') && $user->school_id === $payroll->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted payroll.
     *
     * @param User $user
     * @param Payroll $payroll
     * @return bool
     */
    public function restore(User $user, Payroll $payroll): bool
    {
        return LaratrustFacade::hasPermission('payrolls.restore') && $user->school_id === $payroll->school_id;
    }

    /**
     * Determine whether the user can permanently delete a payroll.
     *
     * @param User $user
     * @param Payroll $payroll
     * @return bool
     */
    public function forceDelete(User $user, Payroll $payroll): bool
    {
        return LaratrustFacade::hasPermission('payrolls.force-delete') && $user->school_id === $payroll->school_id;
    }

    /**
     * Determine whether the user can mark a payroll as paid.
     *
     * @param User $user
     * @param Payroll $payroll
     * @return bool
     */
    public function markAsPaid(User $user, Payroll $payroll): bool
    {
        return LaratrustFacade::hasPermission('payrolls.mark-as-paid') && $user->school_id === $payroll->school_id;
    }
}