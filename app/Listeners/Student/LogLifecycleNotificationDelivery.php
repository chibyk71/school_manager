<?php

namespace App\Listeners\Student;

use App\Models\School;
use App\Services\Student\LifecycleNotificationService;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

/**
 * Marks lifecycle notification deliveries as successful once the channel accepts them.
 *
 * Notification::send() / queue dispatch alone must not set success=true; this listener
 * runs after the channel send (including under Notification::fake() in tests).
 */
class LogLifecycleNotificationDelivery
{
    public function __construct(
        protected LifecycleNotificationService $lifecycleNotifications
    ) {}

    public function handleSent(NotificationSent $event): void
    {
        $extra = $this->extraFrom($event->notification);
        if ($extra === null) {
            return;
        }

        // Only track lifecycle-managed notifications (preference or reminder context).
        if (empty($extra['preference_key']) && empty($extra['reminder_key'])) {
            return;
        }

        $schoolId = $extra['school_id'] ?? null;
        if (! $schoolId) {
            return;
        }

        $school = School::query()->find($schoolId);
        if (! $school) {
            return;
        }

        $context = $this->resolveContext($event->notification, $extra);
        if (! $context) {
            return;
        }

        try {
            $this->lifecycleNotifications->markDelivered(
                $school,
                $context,
                $event->notifiable,
                $event->notification::class,
                $event->channel ?: 'mail',
                $extra['reminder_key'] ?? null,
                [
                    'preference_key' => $extra['preference_key'] ?? null,
                    'phase' => 'delivered',
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to mark lifecycle notification delivered', [
                'error' => $e->getMessage(),
                'notification' => $event->notification::class,
            ]);
        }
    }

    public function handleFailed(NotificationFailed $event): void
    {
        // Explicit no-op for success flag: failures must not create success=true rows.
        // A dispatch_failed / channel failure row may already exist from notify().
        Log::info('Lifecycle notification channel failed', [
            'notification' => $event->notification::class,
            'channel' => $event->channel ?? null,
            'error' => isset($event->data['message']) ? (string) $event->data['message'] : null,
        ]);
    }

    protected function extraFrom(object $notification): ?array
    {
        if (! property_exists($notification, 'extra') || ! is_array($notification->extra)) {
            return null;
        }

        return $notification->extra;
    }

    protected function resolveContext(object $notification, array $extra): ?object
    {
        foreach (['enrollment', 'admission', 'application', 'student'] as $prop) {
            if (property_exists($notification, $prop) && is_object($notification->{$prop})) {
                return $notification->{$prop};
            }
        }

        $type = $extra['lifecycle_type'] ?? null;
        $id = $extra['lifecycle_id'] ?? null;
        if ($type && $id && class_exists($type)) {
            return $type::query()->find($id);
        }

        return null;
    }
}
