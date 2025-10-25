<?php

namespace App\Policies;

use App\Models\Transport\Vehicle\VehicleExpense;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Policy for VehicleExpense model authorization.
 */
class VehicleExpensePolicy
{
    /**
     * Determine whether the user can view any vehicle expenses.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('vehicle-expenses.view');
    }

    /**
     * Determine whether the user can view the vehicle expense.
     *
     * @param User $user
     * @param VehicleExpense $vehicleExpense
     * @return bool
     */
    public function view(User $user, VehicleExpense $vehicleExpense): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('vehicle-expenses.view') && $vehicleExpense->vehicle->school_id === $school?->id;
    }

    /**
     * Determine whether the user can create vehicle expenses.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('vehicle-expenses.create');
    }

    /**
     * Determine whether the user can update the vehicle expense.
     *
     * @param User $user
     * @param VehicleExpense $vehicleExpense
     * @return bool
     */
    public function update(User $user, VehicleExpense $vehicleExpense): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('vehicle-expenses.update') && $vehicleExpense->vehicle->school_id === $school?->id;
    }

    /**
     * Determine whether the user can delete the vehicle expense.
     *
     * @param User $user
     * @param VehicleExpense $vehicleExpense
     * @return bool
     */
    public function delete(User $user, VehicleExpense $vehicleExpense): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('vehicle-expenses.delete') && $vehicleExpense->vehicle->school_id === $school?->id;
    }

    /**
     * Determine whether the user can restore a soft-deleted vehicle expense.
     *
     * @param User $user
     * @param VehicleExpense $vehicleExpense
     * @return bool
     */
    public function restore(User $user, VehicleExpense $vehicleExpense): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('vehicle-expenses.restore') && $vehicleExpense->vehicle->school_id === $school?->id;
    }

    /**
     * Determine whether the user can permanently delete the vehicle expense.
     *
     * @param User $user
     * @param VehicleExpense $vehicleExpense
     * @return bool
     */
    public function forceDelete(User $user, VehicleExpense $vehicleExpense): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('vehicle-expenses.force-delete') && $vehicleExpense->vehicle->school_id === $school?->id;
    }
}
