<?php

namespace App\Jobs\Promotion;

use App\Events\Promotion\PromotionBatchCreated;
use App\Models\Promotion\PromotionBatch;
use App\Services\PromotionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * PopulatePromotionBatch Job
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Background job responsible for populating a newly created PromotionBatch with
 * PromotionStudent records and computing initial system recommendations.
 *
 * This job is dispatched after a PromotionBatch is created (both manually and
 * automatically via TriggerPromotionOnSessionClose listener).
 *
 * Key Features:
 * - Queued for performance (can handle hundreds/thousands of students)
 * - Uses PromotionService::computeStudentRecommendation() for real logic
 * - Updates batch progress counters (total_students, processed_students)
 * - Changes batch status from 'draft' → 'pending' once population is complete
 * - Fires PromotionBatchCreated event when done (for notifications)
 * - Comprehensive error handling and logging
 *
 * Fits into the Promotion Module:
 * - Called after batch creation
 * - Prepares data for the review phase
 * - Heavy lifting is offloaded from the controller/service for better UX
 */

class PopulatePromotionBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes

    public PromotionBatch $batch;

    /**
     * Create a new job instance.
     */
    public function __construct(PromotionBatch $batch)
    {
        $this->batch = $batch;
    }

    /**
     * Execute the job.
     */
    public function handle(PromotionService $service): void
    {
        // Prevent re-processing if already populated
        if ($this->batch->status !== 'draft' && $this->batch->total_students > 0) {
            Log::info('PopulatePromotionBatch: Batch already populated or in progress', [
                'batch_id' => $this->batch->id,
                'status'   => $this->batch->status,
            ]);
            return;
        }

        try {
            Log::info('Starting population of promotion batch', ['batch_id' => $this->batch->id]);

            // Use the service to compute recommendations and create PromotionStudent records
            $this->batch = $service->createPromotionBatchForSession(
                $this->batch->academicSession
            );

            // Update status to pending (ready for review)
            $this->batch->update(['status' => 'pending']);

            // Fire event so listeners can notify approvers
            event(new PromotionBatchCreated($this->batch));

            Log::info('Promotion batch population completed successfully', [
                'batch_id'       => $this->batch->id,
                'total_students' => $this->batch->total_students,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to populate promotion batch', [
                'batch_id' => $this->batch->id,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);

            // Mark batch as failed in metadata for visibility
            $this->batch->update([
                'metadata' => array_merge($this->batch->metadata ?? [], [
                    'population_failed' => true,
                    'error' => $e->getMessage(),
                ]),
            ]);

            // Optionally re-throw to let Laravel retry or mark as failed
            throw $e;
        }
    }
}
