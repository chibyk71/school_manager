<?php

namespace App\Notifications;

use App\Models\Address;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * AddressChangedNotification v1.0 – Unified Notification for Address Changes (Bonus UX Feature)
 *
 * Purpose & Problems Solved:
 * - Provides a user-friendly, queued notification when an address is created, updated, or deleted.
 * - Single notification class handles all actions (created/updated/deleted) via $action parameter – DRY and maintainable.
 * - Supports both email (Markdown template) and database channels for in-app notifications.
 * - Enhances UX: informs owners (e.g., parents, staff) of important address changes made by admins or themselves.
 * - Fully localized-ready: messages can be translated via lang files.
 * - Performance-safe: implements ShouldQueue – email/database write does not block HTTP response.
 * - Rich context: includes formatted address and action-specific messaging.
 *
 * Fits into the Address Management Module:
 * - Triggered via SendAddressChangedNotificationListener on AddressCreated/AddressUpdated events.
 * - Called from AddressService with action ('created', 'updated', 'deleted').
 * - Database channel allows future in-app notification center (e.g., bell icon with unread count).
 * - Email uses clean Markdown template with action button linking to relevant resource (customizable).
 * - Optional bonus: can be disabled per operation in service if not desired.
 *
 * Channels:
 * - mail: Markdown email with subject, greeting, content, and view button.
 * - database: Stored array for in-app listing (type, message, formatted address).
 *
 * Usage:
 *   $notifiable->notify(new AddressChangedNotification($address, 'updated'));
 *
 * Dependencies:
 * - App\Models\Address (for formatted accessor)
 * - Laravel Notification system (queued)
 * - Markdown mail theme configured in config/mail.php
 */
class AddressChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The address that was changed.
     */
    public Address $address;

    /**
     * The action performed (created, updated, deleted).
     */
    public string $action;

    /**
     * Create a new notification instance.
     *
     * @param  Address  $address
     * @param  string   $action  'created' | 'updated' | 'deleted'
     */
    public function __construct(Address $address, string $action = 'updated')
    {
        $this->address = $address;
        $this->action = in_array($action, ['created', 'updated', 'deleted']) ? $action : 'updated';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $actionVerb = match ($this->action) {
            'created' => 'added',
            'updated' => 'updated',
            'deleted' => 'removed',
            default   => 'modified',
        };

        $subject = ucfirst($actionVerb) . ' Address Notification';

        // Optional: customize URL to view the addressable model (e.g., student profile)
        $url = url('/dashboard'); // Replace with dynamic route if available

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . (isset($notifiable->name) ? $notifiable->name : 'there') . ',')
            ->line("An address has been **{$actionVerb}** for your record.")
            ->lineIf($this->action === 'deleted', 'The address has been removed.')
            ->line("**Address:** {$this->address->formatted}")
            ->action('View Your Profile', $url)
            ->line('Thank you for using our application!')
            ->salutation('Regards,');
    }

    /**
     * Get the array representation for database channel.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $actionText = match ($this->action) {
            'created' => 'New address added',
            'updated' => 'Address updated',
            'deleted' => 'Address removed',
            default   => 'Address modified',
        };

        return [
            'type'         => 'address_changed',
            'action'       => $this->action,
            'message'      => $actionText,
            'address_id'   => $this->address->id,
            'formatted'    => $this->address->formatted,
            'addressable_type' => $this->address->addressable_type,
            'addressable_id'   => $this->address->addressable_id,
        ];
    }
}