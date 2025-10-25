<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Laratrust\LaratrustFacade;

/**
 * Policy for Address model authorization.
 */
class AddressPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission(['view-address', 'manage-address']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Address $address): bool
    {
        return LaratrustFacade::hasPermission(['view-address', 'manage-address']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission(['create-address', 'manage-address']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Address $address): bool
    {
        return LaratrustFacade::hasPermission(['update-address', 'manage-address']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Address $address): bool
    {
        return LaratrustFacade::hasPermission(['delete-address', 'manage-address']);
    }
}
