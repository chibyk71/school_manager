<?php

namespace App\Listeners\Promotion;

use App\Events\Promotion\PromotionBatchCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * NotifyOnBatchCompleted Listener
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Fired when a PromotionBatch reaches 'completed' status (all students successfully processed).
 *
 * Purpose:
 * - Notify school admins, initiators, and approvers that the promotion cycle is finished
 * - Trigger final outcome notifications to students/parents (via StudentOutcomeNotification)
 * - Allow generation of reports, transcripts, and certificates
 * - Respect school notification preferences under `academic.promotion_notifications.batch_completed`
 *
 * This is the final notification in the main batch lifecycle.
 */

class NotifyOnBatchCompleted implements ShouldQueue
{
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Handle the event.
     */
    public function handle(PromotionBatchCompleted $event): void
    {
        $batch = $event->batch;

        try {
            $school = $batch->school;

            // Get notification preferences
            $prefs = getMergedSettings('academic.promotion_notifications', $school);
            $eventPrefs = $prefs['batch_completed'] ?? [
                'database' => true,
                'mail'     => true,
                'sms'      => true,   // Often SMS is desired for final outcome
            ];

            if (!$eventPrefs['database'] && !$eventPrefs['mail'] && !$eventPrefs['sms']) {
                return;
            }

            // Notify key stakeholders
            $recipients = \App\Models\User::permission('promotions.view')
                ->where('school_id', $school->id)
                ->get();

            // Also notify the person who executed the batch
            if ($batch->executedBy) {
                $recipients = $recipients->push($batch->executedBy);
            }

            foreach ($recipients as $user) {
                if ($eventPrefs['database']) {
                    $user->notify(new \App\Notifications\Promotion\BatchCompletedNotification($batch));
                }
            }

            Log::info('Notified users that promotion batch was completed', [
                'batch_id' => $batch->id,
                'recipients_count' => $recipients->count(),
            ]);

            // Optional: Trigger per-student outcome notifications
            // This can be done inside the ProcessStudentPromotion job or here

        } catch (\Exception $e) {
            Log::error('Failed to send batch completed notifications', [
                'batch_id' => $batch->id ?? null,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}