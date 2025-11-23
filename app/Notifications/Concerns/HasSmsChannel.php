<?php

namespace App\Notifications\Concerns;

use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * HasSmsChannel Trait
 *
 * Add this trait to any Notification class to automatically deliver via SMS
 * if the notifiable model (e.g. Parent, Student, User) has a phone number.
 *
 * Usage in a notification:
 *
 *   class FeePaymentReminder extends Notification
 *   {
 *       use HasSmsChannel;
 *
 *       public function via($notifiable)
 *       {
 *           return ['sms']; // or ['mail', 'sms']
 *       }
 *
 *       public function toSms($notifiable): string
 *       {
 *           return "Dear {$notifiable->name}, your school fees is overdue. Please pay ASAP.";
 *       }
 *   }
 *
 * @package App\Notifications\Concerns
 */
trait HasSmsChannel
{
    use Queueable;

    /**
     * Deliver the notification via SMS
     */
    public function toSms($notifiable): ?string
    {
        // Fallback: use toArray() if developer forgot to implement toSms()
        if (method_exists($this, 'toArray')) {
            $array = $this->toArray($notifiable);
            return $array['sms'] ?? $array['message'] ?? null;
        }

        return null;
    }

    /**
     * Route the SMS notification
     */
    public function routeNotificationForSms($notification)
    {
        // Support multiple phone fields
        return $this->phone ?? $this->phone_number ?? $this->mobile ?? $this->telephone ?? null;
    }

    /**
     * Send the SMS (called automatically by Laravel's notification system)
     */
    public function sendSms($notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationForSms($notification);

        if (!$phone) {
            Log::info('SMS skipped: no phone number', [
                'notifiable_id' => $notifiable->id,
                'notifiable_type' => get_class($notifiable),
            ]);
            return;
        }

        $message = $notification->toSms($notifiable);

        if (!$message || trim($message) === '') {
            Log::warning('SMS skipped: empty message', [
                'notifiable_id' => $notifiable->id,
            ]);
            return;
        }

        // Use our multi-provider SmsService
        $sent = app(SmsService::class)->send(
            to: $phone,
            message: $message,
            school: $notifiable->school ?? GetSchoolModel()
        );

        if ($sent) {
            Log::info('SMS notification delivered', [
                'notifiable_id' => $notifiable->id,
                'phone' => $phone,
                'message' => substr($message, 0, 100) . '...',
            ]);
        }
    }
}