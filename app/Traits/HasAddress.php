<?php

namespace App\Traits;

use App\Models\Address;
use App\Rules\InDynamicEnum;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * HasAddress Trait v4.0 – Production-Ready Polymorphic Multi-Address Management
 *
 * This trait allows any Eloquent model (Student, Staff, Parent, School, Vendor, Vehicle, etc.)
 * to manage multiple addresses through a polymorphic MorphMany relationship.
 *
 * Features / Problems Solved:
 * - Complete CRUD operations: addAddress, updateAddress, deleteAddress (soft), setPrimaryAddress.
 * - Automatic primary address enforcement – only one primary address per owner at any time.
 * - Centralised, reusable validation with dynamic rules (required on create, sometimes on update).
 * - Hierarchical validation for nnjeim/world (country → state → city existence checks).
 * - Nigeria-first design support: landmark, city_text fallback, address_line_1/2 flexibility.
 * - Multi-tenant safety: automatically assigns current school_id via BelongsToSchool/GetSchoolModel().
 * - Soft-delete integration matching Address model (deleteAllAddresses, restoreAllAddresses, forceDeleteAllAddresses).
 * - Comprehensive error handling and structured logging for production debugging.
 * - Clean separation: trait handles direct model operations; complex logic (events, notifications, geocoding)
 *   can be delegated to AddressService in the future without breaking existing usage.
 * - Helper methods: primaryAddress(), hasAddress().
 *
 * Fits into the Address Management Module:
 * - Used by any addressable model (e.g., class Student extends Model { use HasAddress; }).
 * - Provides consistent API across all resources – no need to repeat address logic in controllers.
 * - Works seamlessly with Address model (v4.0), AddressController, and frontend components
 *   (AddressManager.vue, AddressModal.vue).
 * - Primary flag management is enforced here – UI can trust that only one address is primary.
 *
 * Usage Examples:
 *   $student->addAddress($request->validated(), true);                    // Create & set primary
 *   $student->updateAddress($addressId, $request->validated());           // Partial update
 *   $student->setPrimaryAddress($anotherAddressId);                       // Switch primary
 *   $student->primaryAddress()?->formatted;                               // Display primary
 *
 * Dependencies:
 * - App\Models\Address (with BelongsToSchool, SoftDeletes)
 * - nnjeim/world package for countries/states/cities tables
 * - Global helper GetSchoolModel() for multi-tenant context
 */

trait HasAddress
{
    /**
     * Polymorphic relationship to addresses.
     *
     * @return MorphMany
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /**
     * Get the current primary address (if any).
     *
     * @return Address|null
     */
    public function primaryAddress(): ?Address
    {
        return $this->addresses()->where('is_primary', true)->first();
    }

    /**
     * Add a new address with full validation and optional primary flag.
     *
     * @param array $data       Raw form data
     * @param bool  $isPrimary  Whether to set this as the primary address
     * @return Address
     * @throws ValidationException|\Exception
     */
    public function addAddress(array $data, bool $isPrimary = false): Address
    {
        $validated = $this->validateAddressData($data);

        if ($isPrimary) {
            $this->unsetPrimaryAddress();
        }

        $schoolId = $this->school_id ?? GetSchoolModel()?->id
            ?? throw new \Exception('No active school context found when creating address.');

        try {
            return $this->addresses()->create(array_merge($validated, [
                'is_primary' => $isPrimary,
                'school_id'  => $schoolId,
            ]));
        } catch (\Exception $e) {
            Log::error('Address creation failed', [
                'owner_type' => get_class($this),
                'owner_id'   => $this->id ?? null,
                'data'       => $data,
                'error'      => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update an existing address owned by this model.
     *
     * @param int|string $addressId   Address UUID or ID
     * @param array      $data        Updated data (partial allowed)
     * @param bool       $makePrimary Optionally set as primary after update
     * @return Address
     * @throws ValidationException|\Exception
     */
    public function updateAddress(int|string $addressId, array $data, bool $makePrimary = false): Address
    {
        $validated = $this->validateAddressData($data, forUpdate: true);

        $address = $this->addresses()->findOrFail($addressId);

        if ($makePrimary && !$address->is_primary) {
            $this->unsetPrimaryAddress();
            $validated['is_primary'] = true;
        }

        try {
            $address->update($validated);
            return $address->fresh();
        } catch (\Exception $e) {
            Log::error('Address update failed', [
                'address_id'  => $addressId,
                'owner_type'  => get_class($this),
                'owner_id'    => $this->id ?? null,
                'data'        => $data,
                'error'       => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Soft-delete a single address owned by this model.
     *
     * @param int|string $addressId
     * @return bool
     * @throws \Exception
     */
    public function deleteAddress(int|string $addressId): bool
    {
        $address = $this->addresses()->findOrFail($addressId);

        // If deleting the primary address, unset primary flag first (prevents orphan primary state)
        if ($address->is_primary) {
            $address->update(['is_primary' => false]);
        }

        return $address->delete();
    }

    /**
     * Set a different address as primary (unsets current primary automatically).
     *
     * @param int|string $addressId
     * @return Address
     * @throws \Exception
     */
    public function setPrimaryAddress(int|string $addressId): Address
    {
        $address = $this->addresses()->findOrFail($addressId);

        $this->unsetPrimaryAddress();

        $address->update(['is_primary' => true]);

        return $address->fresh();
    }

    /**
     * Unset any current primary address.
     *
     * @return void
     */
    public function unsetPrimaryAddress(): void
    {
        $this->addresses()->where('is_primary', true)->update(['is_primary' => false]);
    }

    /**
     * Check if this model has at least one address (including trashed if $withTrashed = true).
     *
     * @param bool $withTrashed
     * @return bool
     */
    public function hasAddress(bool $withTrashed = false): bool
    {
        $query = $this->addresses();

        if ($withTrashed) {
            $query = $query->withTrashed();
        }

        return $query->exists();
    }

    /**
     * Soft-delete all addresses belonging to this model.
     *
     * @return void
     */
    public function deleteAllAddresses(): void
    {
        // Unset primary first to keep data consistent
        $this->unsetPrimaryAddress();
        $this->addresses()->delete();
    }

    /**
     * Restore all soft-deleted addresses.
     *
     * @return void
     */
    public function restoreAllAddresses(): void
    {
        $this->addresses()->withTrashed()->restore();
    }

    /**
     * Permanently delete all addresses (including soft-deleted).
     *
     * @return void
     */
    public function forceDeleteAllAddresses(): void
    {
        $this->addresses()->withTrashed()->forceDelete();
    }

    /**
     * Centralised validation logic – used by both create and update.
     *
     * @param array $data
     * @param bool  $forUpdate  If true, fields become 'sometimes' (partial updates allowed)
     * @return array Validated & sanitized data
     * @throws ValidationException
     */
    protected function validateAddressData(array $data, bool $forUpdate = false): array
    {
        $presence = $forUpdate ? 'sometimes' : 'required';

        $rules = [
            // nnjeim/world hierarchical foreign keys
            'country_id' => [$presence, 'exists:countries,id'],
            'state_id'   => ['nullable', 'exists:states,id,country_id,' . ($data['country_id'] ?? 'NULL')],
            'city_id'    => ['nullable', 'exists:cities,id,state_id,' . ($data['state_id'] ?? 'NULL')],

            // Core address fields
            'address_line_1' => [$presence, 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark'       => ['nullable', 'string', 'max:255'],
            'city_text'      => ['nullable', 'string', 'max:100'],
            'postal_code'    => ['nullable', 'string', 'max:20'],

            // Classification
            'type' => [ new InDynamicEnum('type', Address::class), 'required'],

            // Geolocation
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
