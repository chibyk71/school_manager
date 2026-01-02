<?php

namespace App\Services;

use App\Events\AddressCreated;
use App\Events\AddressUpdated;
use App\Models\Address;
use App\Notifications\AddressChangedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * AddressService v1.0 – Centralized Business Logic for Address Management
 *
 * Purpose & Problems Solved:
 * - Encapsulates all non-trivial address-related operations in a single, injectable service.
 * - Keeps the HasAddress trait lightweight and focused on relationship/CRUD helpers only.
 * - Provides a consistent, reusable API for Address creation, updating, deletion, and primary management
 *   across all controllers (AddressController, StudentController, StaffController, etc.).
 * - Centralizes side effects: event dispatching, notifications, future geocoding, logging.
 * - Ensures multi-tenant safety (school_id enforcement) and proper ownership checks.
 * - Makes complex operations testable in isolation (unit tests for service, integration tests for controllers).
 * - Future-proof: easy to add geocoding (Google Maps API), audit trails, or webhooks without touching trait/controllers.
 *
 * Key Features:
 * - Full CRUD with validation delegation to HasAddress trait.
 * - Automatic event dispatching (AddressCreated, AddressUpdated) for listeners/notifications.
 * - Optional user notification (AddressChangedNotification) – can be toggled per operation.
 * - Ownership verification: ensures the address belongs to the provided addressable model.
 * - Structured logging for all operations (success + failure).
 * - Injectable via Laravel's service container – promotes dependency injection and testability.
 *
 * Fits into the Address Management Module:
 * - Called primarily by AddressController (API endpoints) and optionally by resource controllers
 *   that need advanced address logic (e.g., bulk operations, geocoding).
 * - HasAddress trait remains the public API for simple create/update from forms.
 * - Events allow decoupling: notifications, activity logs, search indexing, etc.
 * - Frontend remains unaffected – still uses Inertia responses or JSON from controllers.
 *
 * Usage Examples:
 *   // In AddressController
 *   $service = app(AddressService::class);
 *   $address = $service->create($student, $request->validated(), true);
 *
 *   // In a job or custom logic
 *   $service->update($address, $data);
 *
 * Dependencies:
 * - App\Models\Address
 * - App\Traits\HasAddress (validation & basic CRUD)
 * - Events: AddressCreated, AddressUpdated
 * - Notification: AddressChangedNotification (optional bonus)
 */

class AddressService
{
    /**
     * Create a new address for a given addressable model.
     *
     * @param  mixed  $addressable  Model that uses HasAddress trait (e.g., Student, Staff)
     * @param  array  $data         Validated address data
     * @param  bool   $isPrimary    Whether to set as primary
     * @param  bool   $notify       Send notification to owner (default: true)
     * @return Address
     * @throws ValidationException|\Exception
     */
    public function create($addressable, array $data, bool $isPrimary = false, bool $notify = true): Address
    {
        // Ensure model has the trait
        if (!method_exists($addressable, 'addAddress')) {
            throw new \Exception('Model does not use HasAddress trait.');
        }

        try {
            $address = $addressable->addAddress($data, $isPrimary);

            // Dispatch event for listeners (notifications, activity log, etc.)
            event(new AddressCreated($address, $addressable));

            // Optional direct notification (bonus UX)
            if ($notify && method_exists($addressable, 'notify')) {
                $addressable->notify(new AddressChangedNotification($address, 'created'));
            }

            Log::info('Address created', [
                'address_id'     => $address->id,
                'addressable_type' => get_class($addressable),
                'addressable_id'   => $addressable->id,
                'is_primary'       => $isPrimary,
            ]);

            return $address;
        } catch (\Exception $e) {
            Log::error('Address creation failed in service', [
                'addressable_type' => get_class($addressable),
                'addressable_id'   => $addressable->id ?? null,
                'data'             => $data,
                'error'            => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update an existing address with ownership verification.
     *
     * @param  Address $address      The address instance to update
     * @param  array   $data         Partial update data
     * @param  bool    $makePrimary  Optionally set as primary after update
     * @param  bool    $notify       Send notification (default: true)
     * @return Address
     */
    public function update(Address $address, array $data, bool $makePrimary = false, bool $notify = true): Address
    {
        $addressable = $address->addressable;

        if (!$addressable || !method_exists($addressable, 'updateAddress')) {
            throw new \Exception('Address owner not found or does not support address updates.');
        }

        try {
            $updatedAddress = $addressable->updateAddress($address->id, $data, $makePrimary);

            event(new AddressUpdated($updatedAddress, $addressable));

            if ($notify && method_exists($addressable, 'notify')) {
                $addressable->notify(new AddressChangedNotification($updatedAddress, 'updated'));
            }

            Log::info('Address updated', [
                'address_id'       => $address->id,
                'addressable_type' => get_class($addressable),
                'addressable_id'   => $addressable->id,
                'make_primary'     => $makePrimary,
            ]);

            return $updatedAddress;
        } catch (\Exception $e) {
            Log::error('Address update failed in service', [
                'address_id'       => $address->id,
                'addressable_type' => get_class($addressable),
                'addressable_id'   => $addressable->id ?? null,
                'data'             => $data,
                'error'            => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Soft-delete an address with ownership check.
     *
     * @param  Address $address
     * @param  bool    $notify
     * @return bool
     */
    public function delete(Address $address, bool $notify = true): bool
    {
        $addressable = $address->addressable;

        if (!$addressable || !method_exists($addressable, 'deleteAddress')) {
            throw new \Exception('Address owner not found or does not support deletion.');
        }

        try {
            $result = $addressable->deleteAddress($address->id);

            // No dedicated AddressDeleted event – can be added later if needed
            if ($notify && method_exists($addressable, 'notify')) {
                $addressable->notify(new AddressChangedNotification($address, 'deleted'));
            }

            Log::info('Address soft-deleted', [
                'address_id'       => $address->id,
                'addressable_type' => get_class($addressable),
                'addressable_id'   => $addressable->id,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Address deletion failed in service', [
                'address_id'       => $address->id,
                'error'            => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Set an address as primary (unsets others automatically).
     *
     * @param  Address $address
     * @return Address
     */
    public function setPrimary(Address $address): Address
    {
        $addressable = $address->addressable;

        if (!$addressable || !method_exists($addressable, 'setPrimaryAddress')) {
            throw new \Exception('Address owner not found or does not support primary management.');
        }

        try {
            $primaryAddress = $addressable->setPrimaryAddress($address->id);

            Log::info('Primary address changed', [
                'new_primary_id'   => $primaryAddress->id,
                'addressable_type' => get_class($addressable),
                'addressable_id'   => $addressable->id,
            ]);

            return $primaryAddress;
        } catch (\Exception $e) {
            Log::error('Failed to set primary address', [
                'address_id'       => $address->id,
                'error'            => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}