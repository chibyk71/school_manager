<?php

namespace App\Traits;

use App\Models\Address;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Trait for models that can have multiple addresses.
 */
trait HasAddress
{
    /**
     * Define a polymorphic one-to-many relationship for addresses.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /**
     * Get the primary address for the model.
     *
     * @return \App\Models\Address|null
     */
    public function primaryAddress(): ?Address
    {
        return $this->addresses()->where('is_primary', true)->first();
    }

    /**
     * Add a new address to the model.
     *
     * @param array $data The address data.
     * @param bool $isPrimary Whether the address is primary.
     * @return \App\Models\Address
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     */
    public function addAddress(array $data, bool $isPrimary = false): Address
    {
        $validator = Validator::make($data, [
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country_id' => 'required|exists:countries,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        if ($isPrimary) {
            $this->unsetPrimaryAddress();
        }

        return $this->addresses()->create(array_merge($data, [
            'is_primary' => $isPrimary,
            'school_id' => $this->school_id ?? GetSchoolModel()?->id,
        ]));
    }

    /**
     * Unset the current primary address, if any.
     *
     * @return void
     */
    public function unsetPrimaryAddress(): void
    {
        $this->addresses()->where('is_primary', true)->update(['is_primary' => false]);
    }

    /**
     * Check if the model has an address.
     *
     * @return bool
     */
    public function hasAddress(): bool
    {
        return $this->addresses()->exists();
    }

    /**
     * Delete all addresses for the model (soft delete).
     *
     * @return void
     */
    public function deleteAllAddresses(): void
    {
        $this->addresses()->delete();
    }

    /**
     * Restore all soft-deleted addresses for the model.
     *
     * @return void
     */
    public function restoreAllAddresses(): void
    {
        $this->addresses()->withTrashed()->restore();
    }

    /**
     * Permanently delete all addresses for the model.
     *
     * @return void
     */
    public function forceDeleteAllAddresses(): void
    {
        $this->addresses()->withTrashed()->forceDelete();
    }
}
