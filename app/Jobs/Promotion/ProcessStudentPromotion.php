<?php

namespace App\Jobs\Promotion;

use App\Events\Promotion\PromotionBatchCompleted;
use App\Events\Promotion\StudentDecisionOverridden;
use App\Models\Promotion\PromotionBatch;
use App\Models\Promotion\PromotionHistory;
use App\Models\Promotion\PromotionStudent;
use App\Services\PromotionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProcessStudentPromotion Job
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * The final execution job for an approved PromotionBatch.
 * This job processes each PromotionStudent record, applies the final decision
 * (system recommendation or human override), and creates immutable PromotionHistory records.
 *
 * This is the most critical job in the Promotion Module — it writes the permanent academic record.
 *
 * Key Features:
 * - Chunked processing for large batches (memory safe)
 * - Full transaction per student to ensure data integrity
 * - Respects final_decision (human override takes precedence)
 * - Creates PromotionHistory with full denormalized data
 * - Updates progress counters on the batch (processed_students, failed_students)
 * - Handles errors gracefully per student (continues processing others)
 * - Fires PromotionBatchCompleted event when finished
 * - Queued with dedicated queue for long-running operations
 *
 * Fits into the Promotion Module:
 * - Dispatched by PromotionService::executeApprovedBatch()
 * - Called only after batch status = 'approved'
 * - Final step before the batch becomes immutable
 */

class ProcessStudentPromotion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

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
    public function handle(): void
    {
        if ($this->batch->status !== 'executing') {
            Log::warning('ProcessStudentPromotion: Batch is not in executing state', [
                'batch_id' => $this->batch->id,
                'status'   => $this->batch->status,
            ]);
            return;
        }

        Log::info('Starting execution of promotion batch', ['batch_id' => $this->batch->id]);

        $processed = 0;
        $failed = 0;

        try {
            // Process students in chunks to keep memory usage low
            PromotionStudent::where('promotion_batch_id', $this->batch->id)
                ->where('is_processed', false)
                ->chunk(100, function ($students) use (&$processed, &$failed) {
                    foreach ($students as $studentRecord) {
                        $this->processSingleStudent($studentRecord, $processed, $failed);
                    }
                });

            // Mark batch as completed
            $this->batch->update([
                'status' => 'completed',
                'processed_students' => $this->batch->total_students,
                'failed_students' => $failed,
            ]);

            // Fire completion event
            event(new PromotionBatchCompleted($this->batch));

            Log::info('Promotion batch execution completed', [
                'batch_id' => $this->batch->id,
                'processed' => $processed,
                'failed' => $failed,
            ]);

        } catch (\Exception $e) {
            Log::error('Critical failure in ProcessStudentPromotion job', [
                'batch_id' => $this->batch->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->batch->update([
                'metadata' => array_merge($this->batch->metadata ?? [], [
                    'execution_failed' => true,
                    'error' => $e->getMessage(),
                ]),
            ]);

            throw $e;
        }
    }

    /**
     * Process a single student with full transaction safety.
     */
    protected function processSingleStudent(PromotionStudent $studentRecord, int &$processed, int &$failed): void
    {
        try {
            DB::transaction(function () use ($studentRecord, &$processed, &$failed) {
                $finalOutcome = $studentRecord->final_outcome; // respects override

                // Create permanent history record
                PromotionHistory::create([
                    'school_id' => $studentRecord->promotionBatch->school_id,
                    'student_id' => $studentRecord->student_id,
                    'promotion_batch_id' => $studentRecord->promotion_batch_id,
                    'promotion_student_id' => $studentRecord->id,
                    'from_academic_session_id' => $studentRecord->promotionBatch->academic_session_id,
                    'to_academic_session_id' => in_array($finalOutcome, ['promote', 'graduate'])
                        ? $this->getNextSessionId($studentRecord)
                        : null,
                    'from_class_section_id' => $studentRecord->current_class_section_id,
                    'to_class_section_id' => $studentRecord->next_class_section_id,
                    'outcome' => $finalOutcome,
                    'was_overridden' => $studentRecord->isOverridden(),
                    'override_reason' => $studentRecord->override_reason,
                    'average_score' => $studentRecord->average_score,
                    'failed_subjects_count' => $studentRecord->failed_subjects_count,
                    'executed_by' => $studentRecord->promotionBatch->executed_by,
                    'executed_at' => now(),
                ]);

                // Mark student record as processed
                $studentRecord->update([
                    'is_processed' => true,
                    'processed_at' => now(),
                ]);

                $processed++;
            });

        } catch (\Exception $e) {
            $failed++;

            Log::error('Failed to process student in promotion batch', [
                'batch_id' => $this->batch->id,
                'student_id' => $studentRecord->student_id,
                'error' => $e->getMessage(),
            ]);

            // Record error on student record but continue with others
            $studentRecord->update([
                'processing_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get next academic session ID (for promote/graduate cases).
     * Simple implementation — improve if you have a better session sequence.
     */
    protected function getNextSessionId(PromotionStudent $studentRecord): ?int
    {
        // For now return null (you can enhance this later with proper session progression)
        return null;
    }
}
