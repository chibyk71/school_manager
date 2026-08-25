<?php

namespace App\Listeners\Promotion;

use App\Events\Promotion\PromotionBatchApproved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * NotifyOnBatchApproved Listener
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Fired when a PromotionBatch is approved (status changed to 'approved').
 *
 * Purpose:
 * - Notify the original initiator and all users with 'promotions.execute' permission
 *   that the batch is now ready for execution.
 * - Respect school-specific notification preferences under `academic.promotion_notifications.batch_approved`
 * - Provide clear audit trail
 *
 * This listener helps maintain smooth workflow from approval to execution.
 */

class NotifyOnBatchApproved implements ShouldQueue
{
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Handle the event.
     */
    public function handle(PromotionBatchApproved $event): void
    {
        $batch = $event->batch;

        try {
            $school = $batch->school;

            // Get notification preferences for this specific event
            $prefs = getMergedSettings('academic.promotion_notifications', $school);
            $eventPrefs = $prefs['batch_approved'] ?? [
                'database' => true,
                'mail'     => true,
                'sms'      => false,
            ];

            if (!$eventPrefs['database'] && !$eventPrefs['mail'] && !$eventPrefs['sms']) {
                return; // No channels enabled for this event
            }

            // Notify users who can execute the batch
            $executors = \App\Models\User::permission('promotions.execute')
                ->where('school_id', $school->id)
                ->get();

            // Also notify the person who initiated the batch (if different)
            $initiator = $batch->initiatedBy;
            if ($initiator && !$executors->contains('id', $initiator->id)) {
                $executors->push($initiator);
            }

            foreach ($executors as $user) {
                if ($eventPrefs['database']) {
                    $user->notify(new \App\Notifications\Promotion\BatchApprovedNotification($batch));
                }
                // Mail & SMS are handled inside the Notification class via channels()
            }

            Log::info('Notified users that promotion batch was approved and ready for execution', [
                'batch_id' => $batch->id,
                'executors_count' => $executors->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send batch approved notifications', [
                'batch_id' => $batch->id ?? null,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}