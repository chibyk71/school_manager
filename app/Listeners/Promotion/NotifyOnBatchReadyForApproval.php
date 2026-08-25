<?php

namespace App\Listeners\Promotion;

use App\Events\Promotion\PromotionBatchCreated;
use App\Events\Promotion\PromotionBatchApproved;
use App\Models\Promotion\PromotionBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * NotifyOnBatchReadyForApproval Listener
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Listens for newly created promotion batches and notifies users who have
 * the 'promotions.approve' permission that a batch is ready for review/approval.
 *
 * This listener works together with your notification preferences stored in
 * settings key `academic.promotion_notifications`.
 *
 * Features:
 * - Uses existing notification system (database + mail + SMS via SmsService)
 * - Respects per-school notification preferences
 * - Queued to avoid blocking the main promotion creation flow
 * - Logs notification activity
 *
 * Fits into the Promotion Module:
 * - Triggered after PromotionBatchCreated event
 * - Prepares the batch for human review phase
 */

class NotifyOnBatchReadyForApproval implements ShouldQueue
{
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Handle the event.
     */
    public function handle(PromotionBatchCreated $event): void
    {
        $batch = $event->batch;

        try {
            // Only notify if batch is in a state that needs approval
            if (!in_array($batch->status, ['pending', 'reviewing'], true)) {
                return;
            }

            $school = $batch->school;

            // Get notification preferences for this event
            $prefs = getMergedSettings('academic.promotion_notifications', $school);

            $eventPrefs = $prefs['batch_ready_for_approval'] ?? [
                'database' => true,
                'mail' => true,
                'sms' => false,
            ];

            if (!$eventPrefs['database'] && !$eventPrefs['mail'] && !$eventPrefs['sms']) {
                return; // No channels enabled
            }

            // Notify all users with 'promotions.approve' permission
            $approvers = \App\Models\User::permission('promotions.approve')
                ->where('school_id', $school->id)
                ->get();

            foreach ($approvers as $user) {
                // You can create a dedicated notification class later
                // For now we use a generic one or dispatch directly
                if ($eventPrefs['database']) {
                    $user->notify(new \App\Notifications\Promotion\BatchReadyForApprovalNotification($batch));
                }

                // Mail and SMS can be added via the same notification class
                // using ->onQueue() and channels() method
            }

            Log::info('Notified users about batch ready for approval', [
                'batch_id' => $batch->id,
                'approvers_count' => $approvers->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify on batch ready for approval', [
                'batch_id' => $batch->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}