<?php

namespace App\Jobs\Promotion;

use App\Events\Promotion\PromotionBatchCompleted;
use App\Models\Promotion\PromotionBatch;
use App\Models\Promotion\PromotionHistory;
use App\Models\Promotion\PromotionStudent;
use App\States\Promotion\Completed;
use App\States\Promotion\Executing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Final execution job for an approved PromotionBatch.
 *
 * Processes each PromotionStudent, applies the final decision
 * (recommendation or override), and writes immutable PromotionHistory rows.
 * Transitions the batch Executing → Completed when finished.
 */
class ProcessStudentPromotion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public PromotionBatch $batch)
    {
        $this->onQueue('promotions');
    }

    public function handle(): void
    {
        $this->batch->refresh();

        if (! $this->batch->status->equals(Executing::class)) {
            Log::warning('ProcessStudentPromotion: batch is not in executing state', [
                'batch_id' => $this->batch->id,
                'status' => (string) $this->batch->status,
            ]);

            return;
        }

        Log::info('Starting execution of promotion batch', ['batch_id' => $this->batch->id]);

        $processed = 0;
        $failed = 0;

        try {
            PromotionStudent::query()
                ->where('promotion_batch_id', $this->batch->id)
                ->where('is_processed', false)
                ->chunkById(100, function ($students) use (&$processed, &$failed) {
                    foreach ($students as $studentRecord) {
                        $this->processSingleStudent($studentRecord, $processed, $failed);
                    }
                });

            $this->batch->refresh();
            $this->batch->processed_students = $this->batch->total_students;
            $this->batch->failed_students = $failed;
            $this->batch->save();
            $this->batch->status->transitionTo(Completed::class);

            event(new PromotionBatchCompleted($this->batch->fresh()));

            Log::info('Promotion batch execution completed', [
                'batch_id' => $this->batch->id,
                'processed' => $processed,
                'failed' => $failed,
            ]);
        } catch (\Throwable $e) {
            Log::error('Critical failure in ProcessStudentPromotion job', [
                'batch_id' => $this->batch->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->batch->refresh();
            $metadata = $this->batch->metadata ?? [];
            $metadata['execution_failed'] = true;
            $metadata['error'] = $e->getMessage();
            $this->batch->metadata = $metadata;
            $this->batch->save();

            throw $e;
        }
    }

    protected function processSingleStudent(PromotionStudent $studentRecord, int &$processed, int &$failed): void
    {
        try {
            DB::transaction(function () use ($studentRecord, &$processed) {
                $studentRecord->loadMissing('promotionBatch');
                $finalOutcome = $studentRecord->final_outcome;

                PromotionHistory::create([
                    'school_id' => $studentRecord->promotionBatch->school_id,
                    'student_id' => $studentRecord->student_id,
                    'promotion_batch_id' => $studentRecord->promotion_batch_id,
                    'promotion_student_id' => $studentRecord->id,
                    'from_academic_session_id' => $studentRecord->promotionBatch->academic_session_id,
                    'to_academic_session_id' => in_array($finalOutcome, ['promote', 'graduate'], true)
                        ? $this->resolveNextSessionId($studentRecord)
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

                $studentRecord->update([
                    'is_processed' => true,
                    'processed_at' => now(),
                ]);

                $processed++;
            });
        } catch (\Throwable $e) {
            $failed++;

            Log::error('Failed to process student in promotion batch', [
                'batch_id' => $this->batch->id,
                'student_id' => $studentRecord->student_id,
                'error' => $e->getMessage(),
            ]);

            $studentRecord->update([
                'processing_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Next academic session for promote/graduate history rows.
     * Returns null until session sequencing is wired; history still records from_session.
     */
    protected function resolveNextSessionId(PromotionStudent $studentRecord): ?string
    {
        return null;
    }
}
