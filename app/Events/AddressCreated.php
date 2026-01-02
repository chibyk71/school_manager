<?php

namespace App\Events;

use App\Models\Address;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * AddressCreated Event v1.0 – Fired When a New Address is Successfully Created
 *
 * Purpose & Problems Solved:
 * - Provides a decoupled, event-driven hook for side effects after address creation.
 * - Allows multiple listeners (notifications, activity logging, search indexing, webhooks, etc.)
 *   without bloating AddressService or controllers.
 * - Carries both the created Address instance and its polymorphic owner (addressable model)
 *   for rich context in listeners.
 * - Queueable by default (SerializesModels) – safe for queued listeners/notifications.
 * - Non-broadcasting (no real-time needed for address changes) – keeps it lightweight.
 *
 * Fits into the Address Management Module:
 * - Dispatched from AddressService::create() after successful creation.
 * - Primary listener: SendAddressChangedNotificationListener (queues AddressChangedNotification).
 * - Future-proof: Easy to add more listeners (e.g., sync to external CRM, update maps cache).
 *
 * Usage:
 *   event(new AddressCreated($address, $addressable));
 *
 * Dependencies:
 * - App\Models\Address
 * - Laravel's event system (no broadcasting required)
 */
class AddressCreated
{
    use Dispatchable, SerializesModels;

    /**
     * The newly created address instance.
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
     * @param  mixed    $addressable  The model that owns the address
     */
    public function __construct(Address $address, $addressable)
    {
        $this->address = $address;
        $this->addressable = $addressable;
    }
}