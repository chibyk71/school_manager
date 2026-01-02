<?php

namespace App\Events;

use App\Models\Address;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * AddressUpdated Event v1.0 – Fired When an Existing Address is Successfully Updated
 *
 * Purpose & Problems Solved:
 * - Decouples update side effects from AddressService (notifications, logging, auditing).
 * - Provides full context: updated Address + polymorphic owner.
 * - Enables queued processing for performance (e.g., email/SMS notifications).
 * - Non-broadcasting – address changes do not require real-time push in most cases.
 * - Consistent with AddressCreated event for uniform listener handling.
 *
 * Fits into the Address Management Module:
 * - Dispatched from AddressService::update() after successful update.
 * - Primary listener: SendAddressChangedNotificationListener (queues AddressChangedNotification with 'updated' action).
 * - Extensible: Add listeners for audit trails, geocoding refresh, cache invalidation.
 *
 * Usage:
 *   event(new AddressUpdated($updatedAddress, $addressable));
 *
 * Dependencies:
 * - App\Models\Address
 * - Laravel's event system
 */
class AddressUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * The updated address instance (fresh from DB).
     */
    public Address $address;

    /**
     * The polymorphic owner model (Student, Staff, School, etc.).
     *
     * @var mixed
     */
    public $addressable;

    /**
     * Create a new event instance.
     *
     * @param  Address  $address
     * @param  mixed    $addressable
     */
    public function __construct(Address $address, $addressable)
    {
        $this->address = $address;
        $this->addressable = $addressable;
    }
}