<?php

namespace App\Policies;

use App\Models\Transport\Vehicle\Vehicle;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Policy for Vehicle model authorization.
 */
class VehiclePolicy
{
    /**
     * Determine whether the user can view any vehicles.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('vehicles.view');
    }

    /**
     * Determine whether the user can view the vehicle.
     *
     * @param User $user
     * @param Vehicle $vehicle
     * @return bool
     */
    public function view(User $user, Vehicle $vehicle): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('vehicles.view') && $vehicle->school_id === $school?->id;
    }

    /**
     * Determine whether the user can create vehicles.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('vehicles.create');
    }

    /**
     * Determine whether the user can update the vehicle.
     *
     * @param User $user
     * @param Vehicle $vehicle
     * @return bool
     */
    public function update(User $user, Vehicle $vehicle): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('vehicles.update') && $vehicle->school_id === $school?->id;
    }

    /**
     * Determine whether the user can delete the vehicle.
     *
     * @param User $user
     * @param Vehicle $vehicle
     * @return bool
     */
    public function delete(User $user, Vehicle $vehicle): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('vehicles.delete') && $vehicle->school_id === $school?->id;
    }

    /**
     * Determine whether the user can restore a soft-deleted vehicle.
     *
     * @param User $user
     * @param Vehicle $vehicle
     * @return bool
     */
    public function restore(User $user, Vehicle $vehicle): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('vehicles.restore') && $vehicle->school_id === $school?->id;
    }

    /**
     * Determine whether the user can permanently delete the vehicle.
     *
     * @param User $user
     * @param Vehicle $vehicle
     * @return bool
     */
    public function forceDelete(User $user, Vehicle $vehicle): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('vehicles.force-delete') && $vehicle->school_id === $school?->id;
    }

    /**
     * Determine whether the user can assign a driver to the vehicle.
     *
     * @param User $user
     * @param Vehicle $vehicle
     * @return bool
     */
    public function assignDriver(User $user, Vehicle $vehicle): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('vehicles.assign-driver') && $vehicle->school_id === $school?->id;
    }
}
