<?php

namespace App\Traits;

use App\Models\Address;

trait HasAddress
{
    /**
     * Define a polymorphic one-to-many relationship for addresses.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /**
     * Get the primary address for the model.
     *
     * @return \App\Models\Address|null
     */
    public function primaryAddress()
    {
        return $this->addresses()->where('is_primary', true)->first();
    }

    /**
     * Add a new address to the model.
     *
     * @param array $data
     * @param bool $isPrimary
     * @return \App\Models\Address
     */
    public function addAddress(array $data, $isPrimary = false)
    {
        if ($isPrimary) {
            // Unset the current primary address
            $this->unsetPrimaryAddress();
        }

        return $this->addresses()->create(array_merge($data, ['is_primary' => $isPrimary]));
    }

    /**
     * Unset the current primary address, if any.
     *
     * @return void
     */
    public function unsetPrimaryAddress()
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
     * Delete all addresses for the model.
     *
     * @return void
     */
    public function deleteAllAddresses()
    {
        $this->addresses()->delete();
    }
}
