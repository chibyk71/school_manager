<?php
// app/Listeners/CreatePromotionBatch.php

namespace App\Listeners;

use App\Events\AcademicSessionCompleted;
use App\Models\Promotion\PromotionBatch;
use App\Notifications\PromotionBatchReadyForApproval;
use App\Services\PromotionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Listener: When AcademicSessionCompleted event fires,
 * this creates the promotion batch and notifies the principal.
 */
class CreatePromotionBatch implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'promotions';
    public $tries = 3;
    public $backoff = [30, 60, 120];

    protected PromotionService $promotionService;

    public function __construct(PromotionService $promotionService)
    {
        $this->promotionService = $promotionService;
    }

    /**
     * Handle the event.
     *
     * @param AcademicSessionCompleted $event
     * @return void
     */
    public function handle(AcademicSessionCompleted $event): void
    {
        $batch = $event->batch;

        Log::info('Promotion batch creation started via listener', [
            'batch_id' => $batch->id,
            'session' => $batch->academicSession?->name,
        ]);

        try {
            // The batch is already created in PromotionService
            // Here we just notify stakeholders

            $this->notifyPrincipal($batch);
            $this->notifyAdmins($batch);

            Log::info('Promotion batch ready for review', [
                'batch_id' => $batch->id,
                'total_students' => $batch->total_students,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process promotion batch notification', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);

            // Optional: mark batch as failed
            $batch->update(['status' => 'failed']);
            throw $e;
        }
    }

    /**
     * Notify the school principal.
     */
    protected function notifyPrincipal(PromotionBatch $batch): void
    {
        $principal = $batch->school->principal; // assuming you have this relation

        if ($principal) {
            $principal->notify(new PromotionBatchReadyForApproval($batch));
        }
    }

    /**
     * Notify school admins (optional).
     */
    protected function notifyAdmins(PromotionBatch $batch): void
    {
        $admins = $batch->school->admins()->get(); // assuming relation

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new PromotionBatchReadyForApproval($batch));
        }
    }
}