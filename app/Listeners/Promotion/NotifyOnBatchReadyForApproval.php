<?php

namespace App\Listeners\Promotion;

use App\Events\Promotion\PromotionBatchReadyForReview;
use App\Notifications\Promotion\BatchReadyForApprovalNotification;
use App\States\Promotion\Pending;
use App\States\Promotion\Reviewing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Notifies users with promotions.approve when a batch is ready for review.
 * Respects settings key academic.promotion_notifications.batch_ready_for_approval.
 */
class NotifyOnBatchReadyForApproval implements ShouldQueue
{
    public int $tries = 3;

    public function handle(PromotionBatchReadyForReview $event): void
    {
        $batch = $event->batch->fresh(['school']);

        try {
            if (! ($batch->status instanceof Pending || $batch->status instanceof Reviewing)) {
                return;
            }

            $school = $batch->school;
            if (! $school) {
                return;
            }

            $prefs = getMergedSettings('academic.promotion_notifications', $school);
            $eventPrefs = $prefs['batch_ready_for_approval'] ?? [
                'database' => true,
                'mail' => true,
                'sms' => false,
            ];

            if (empty($eventPrefs['database']) && empty($eventPrefs['mail']) && empty($eventPrefs['sms'])) {
                return;
            }

            $approvers = \App\Models\User::permission('promotions.approve')
                ->where('school_id', $school->id)
                ->get();

            foreach ($approvers as $user) {
                if (! empty($eventPrefs['database']) || ! empty($eventPrefs['mail']) || ! empty($eventPrefs['sms'])) {
                    $user->notify(new BatchReadyForApprovalNotification($batch));
                }
            }

            Log::info('Notified users about batch ready for approval', [
                'batch_id' => $batch->id,
                'approvers_count' => $approvers->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to notify on batch ready for approval', [
                'batch_id' => $batch->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
