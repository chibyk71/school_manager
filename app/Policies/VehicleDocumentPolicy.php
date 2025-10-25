<?php

namespace App\Policies;

use App\Models\Transport\Vehicle\VehicleDocument;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Policy for VehicleDocument model authorization.
 */
class VehicleDocumentPolicy
{
    /**
     * Determine whether the user can view any vehicle documents.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('vehicle-documents.view');
    }

    /**
     * Determine whether the user can view the vehicle document.
     *
     * @param User $user
     * @param VehicleDocument $vehicleDocument
     * @return bool
     */
    public function view(User $user, VehicleDocument $vehicleDocument): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('vehicle-documents.view') && $vehicleDocument->vehicle->school_id === $school?->id;
    }

    /**
     * Determine whether the user can create vehicle documents.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('vehicle-documents.create');
    }

    /**
     * Determine whether the user can update the vehicle document.
     *
     * @param User $user
     * @param VehicleDocument $vehicleDocument
     * @return bool
     */
    public function update(User $user, VehicleDocument $vehicleDocument): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('vehicle-documents.update') && $vehicleDocument->vehicle->school_id === $school?->id;
    }

    /**
     * Determine whether the user can delete the vehicle document.
     *
     * @param User $user
     * @param VehicleDocument $vehicleDocument
     * @return bool
     */
    public function delete(User $user, VehicleDocument $vehicleDocument): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('vehicle-documents.delete') && $vehicleDocument->vehicle->school_id === $school?->id;
    }

    /**
     * Determine whether the user can restore a soft-deleted vehicle document.
     *
     * @param User $user
     * @param VehicleDocument $vehicleDocument
     * @return bool
     */
    public function restore(User $user, VehicleDocument $vehicleDocument): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('vehicle-documents.restore') && $vehicleDocument->vehicle->school_id === $school?->id;
    }

    /**
     * Determine whether the user can permanently delete the vehicle document.
     *
     * @param User $user
     * @param VehicleDocument $vehicleDocument
     * @return bool
     */
    public function forceDelete(User $user, VehicleDocument $vehicleDocument): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('vehicle-documents.force-delete') && $vehicleDocument->vehicle->school_id === $school?->id;
    }
}
