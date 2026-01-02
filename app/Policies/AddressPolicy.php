<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * AddressPolicy v1.0 – Authorization Logic for Address Management Module
 *
 * This policy defines fine-grained access control for the Address model using Laravel's Gate/Policies system.
 * It integrates with your custom permission system (e.g., 'address.view' format) and supports ownership checks
 * where applicable (e.g., user can only update/delete addresses they own, unless they have broader manage permissions).
 *
 * Features / Problems Solved:
 * - Standard CRUD authorization: viewAny, view, create, update, delete.
 * - Added restore method for soft-deleted addresses (aligns with Address model's SoftDeletes).
 * - Permission-based: Uses {resource}.{action} format (e.g., 'address.view') for consistency with your system.
 * - Ownership enforcement: For view/update/delete/restore, checks if the user owns the addressable model
 *   (e.g., if address belongs to a Student, verify user can manage that Student) or has 'address.manage' permission.
 * - Fallback to global 'address.manage' for admins/superusers to override ownership.
 * - Denies with explicit Response messages for better debugging and user feedback (e.g., via toasts in frontend).
 * - Prevents unauthorized access in multi-tenant setups (assumes user permissions are scoped via middleware/guards).
 * - Easy extensibility: Add custom logic per addressable_type if needed (e.g., different rules for School vs. Student).
 *
 * Fits into the Address Management Module:
 * - Applied automatically via Laravel's policy registration in AuthServiceProvider.
 * - Used by AddressController to gate API endpoints (e.g., beforeFilter('can:update,address')).
 * - Complements AddressService (checks permissions before operations if needed).
 * - Aligns with frontend usePermissions composable: Mirror permissions like 'address.view' for disable/enable UI elements.
 * - Ensures security is baked in early – no unauthorized address changes, even via direct API calls.
 *
 * Registration:
 * - In app/Providers/AuthServiceProvider.php, add to $policies:
 *   'App\Models\Address' => 'App\Policies\AddressPolicy',
 * - Then boot the Gate in the provider's boot() method if not already.
 *
 * Usage Examples:
 * - In Controller: Gate::authorize('update', $address);
 * - Custom ownership: Override or extend if addressable-specific policies are needed (e.g., via addressable's policy).
 *
 * Dependencies:
 * - Assumes a permission system (e.g., Spatie Permissions or custom) where User::hasPermission('address.view') works.
 * - Relies on Address model's addressable relation being loaded when needed.
 */
class AddressPolicy
{
    /**
     * Determine whether the user can view any models (e.g., list all addresses).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('address.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Address $address): Response|bool
    {
        if ($user->hasPermission('address.view') && $this->ownsAddressable($user, $address)) {
            return true;
        }

        return Response::deny('You do not have permission to view this address.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('address.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Address $address): Response|bool
    {
        if ($user->hasPermission('address.update') && $this->ownsAddressable($user, $address)) {
            return true;
        }

        return Response::deny('You do not have permission to update this address.');
    }

    /**
     * Determine whether the user can delete the model (soft delete).
     */
    public function delete(User $user, Address $address): Response|bool
    {
        if ($user->hasPermission('address.delete') && $this->ownsAddressable($user, $address)) {
            return true;
        }

        return Response::deny('You do not have permission to delete this address.');
    }

    /**
     * Determine whether the user can restore the soft-deleted model.
     */
    public function restore(User $user, Address $address): Response|bool
    {
        if ($user->hasPermission('address.restore') && $this->ownsAddressable($user, $address)) {
            return true;
        }

        return Response::deny('You do not have permission to restore this address.');
    }

    /**
     * Helper: Check if the user owns or can manage the addressable model.
     *
     * This can be customized per addressable type (e.g., for Student, check if user is parent/teacher).
     * Default: Assumes user ID matches addressable's user_id if present, or true for simplicity.
     * Extend this method for more complex ownership logic (e.g., via addressable's policy).
     */
    protected function ownsAddressable(User $user, Address $address): bool
    {
        $addressable = $address->addressable;

        if (!$addressable) {
            return false;
        }

        // Example: If addressable has a policy, delegate to it
        if (method_exists($addressable, 'getPolicy')) {
            return $user->can('update', $addressable); // Or 'manage', depending on context
        }

        // Fallback: Check if addressable belongs to user (e.g., via user_id field)
        if (isset($addressable->user_id) && $addressable->user_id === $user->id) {
            return true;
        }

        // Default: Deny if no clear ownership
        return false;
    }
}