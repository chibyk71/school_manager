<?php

namespace App\Services;

use App\Models\Address;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * AddressService — thin adapter for HTTP callers (AddressController).
 *
 * Phase 2: HasAddress is the canonical reusable backend capability.
 * This service no longer owns competing CRUD logic; it resolves the
 * addressable owner and delegates to HasAddress methods.
 *
 * Events, notifications, and geocoding are intentionally not dispatched
 * from the capability layer. Broader HTTP/API cleanup belongs to Phase 3.
 * The $notify parameters are retained for call-site compatibility only
 * and currently have no effect.
 */
class AddressService
{
    /**
     * Create an address for the given owner via HasAddress.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(Model $addressable, array $data, bool $isPrimary = false, bool $notify = false): Address
    {
        $this->assertHasAddress($addressable);

        return $addressable->addAddress($data, $isPrimary);
    }

    /**
     * Update an address via its owner capability.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(Address $address, array $data, bool $makePrimary = false, bool $notify = false): Address
    {
        $addressable = $this->resolveOwner($address);

        return $addressable->updateAddress($address, $data, $makePrimary);
    }

    /**
     * Permanently delete an address via its owner capability.
     */
    public function delete(Address $address, bool $notify = false): bool
    {
        $addressable = $this->resolveOwner($address);

        return $addressable->deleteAddress($address);
    }

    /**
     * Set primary via owner capability.
     */
    public function setPrimary(Address $address): Address
    {
        $addressable = $this->resolveOwner($address);

        return $addressable->setPrimaryAddress($address);
    }

    /**
     * @throws \RuntimeException
     */
    protected function resolveOwner(Address $address): Model
    {
        $addressable = $address->addressable;

        if (! $addressable instanceof Model) {
            throw new \RuntimeException('Address owner not found.');
        }

        $this->assertHasAddress($addressable);

        return $addressable;
    }

    /**
     * @throws \RuntimeException
     */
    protected function assertHasAddress(Model $addressable): void
    {
        if (! method_exists($addressable, 'addAddress')) {
            throw new \RuntimeException(
                get_class($addressable).' does not use HasAddress.'
            );
        }
    }
}
