<?php

namespace App\Listeners\Student;

use App\Services\Student\LifecycleNotificationService;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

/**
 * Finalizes lifecycle NotificationLog rows after channel outcome.
 *
 * Correlation: notification->extra['dispatch_id'] matches NotificationLog.metadata.dispatch_id
 * written at queue/dispatch time with success=false / phase=dispatched.
 *
 * Queueing alone never sets success=true.
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

        // Only lifecycle-managed notifications carry preference_key / reminder_key / dispatch_id.
        if (empty($extra['preference_key']) && empty($extra['reminder_key']) && empty($extra['dispatch_id'])) {
            return;
        }

        $dispatchId = $extra['dispatch_id'] ?? null;
        if (! is_string($dispatchId) || $dispatchId === '') {
            Log::warning('Lifecycle NotificationSent without dispatch_id; cannot finalize log', [
                'notification' => $event->notification::class,
            ]);

            return;
        }

        try {
            $this->lifecycleNotifications->markDispatchOutcome($dispatchId, true, null);
        } catch (\Throwable $e) {
            Log::warning('Failed to mark lifecycle notification delivered', [
                'error' => $e->getMessage(),
                'dispatch_id' => $dispatchId,
                'notification' => $event->notification::class,
            ]);
        }
    }

    public function handleFailed(NotificationFailed $event): void
    {
        $extra = $this->extraFrom($event->notification);
        $dispatchId = is_array($extra) ? ($extra['dispatch_id'] ?? null) : null;

        $error = null;
        if (isset($event->data) && is_array($event->data)) {
            $error = isset($event->data['message']) ? (string) $event->data['message'] : json_encode($event->data);
        }

        if (is_string($dispatchId) && $dispatchId !== '') {
            try {
                $this->lifecycleNotifications->markDispatchOutcome($dispatchId, false, $error);
            } catch (\Throwable $e) {
                Log::warning('Failed to mark lifecycle notification failed', [
                    'error' => $e->getMessage(),
                    'dispatch_id' => $dispatchId,
                ]);
            }

            return;
        }

        Log::info('Lifecycle notification channel failed without dispatch_id', [
            'notification' => $event->notification::class,
            'channel' => $event->channel ?? null,
            'error' => $error,
        ]);
    }

    protected function extraFrom(object $notification): ?array
    {
        if (! property_exists($notification, 'extra') || ! is_array($notification->extra)) {
            return null;
        }

        return $notification->extra;
    }
}
