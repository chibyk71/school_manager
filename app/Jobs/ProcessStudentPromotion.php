<?php
// app/Jobs/ProcessStudentPromotion.php

namespace App\Jobs;

use App\Models\Academic\AcademicSession;
use App\Models\Promotion\PromotionBatch;
use App\Models\Promotion\PromotionHistory;
use App\Models\Promotion\PromotionStudent;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProcessStudentPromotion
 *
 * One job per student. Runs in a Laravel Bus Batch.
 * Fully atomic, safe, and observable.
 */
class ProcessStudentPromotion implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'promotions';
    public $tries = 3;
    public $backoff = [10, 30, 60];
    public $timeout = 300; // 5 minutes per student (very safe)

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PromotionBatch $batch
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch->canceled()) {
            Log::info('Promotion batch canceled, stopping job.', ['batch_id' => $this->batch->id]);
            return;
        }

        // Get next unprocessed student
        $promotionStudent = PromotionStudent::where('promotion_batch_id', $this->batch->id)
            ->where('is_processed', false)
            ->with(['student', 'currentSection', 'nextSection'])
            ->lockForUpdate()
            ->first();

        if (!$promotionStudent) {
            // All done
            if ($this->batch->processed_students >= $this->batch->total_students) {
                $this->batch->update([
                    'status' => 'completed',
                    'executed_at' => now(),
                ]);
                Log::info('Promotion batch fully completed', ['batch_id' => $this->batch->id]);
            }
            return;
        }

        DB::transaction(function () use ($promotionStudent) {
            $student = $promotionStudent->student;
            $currentSection = $promotionStudent->currentSection;
            $nextSection = $promotionStudent->nextSection;
            $decision = $promotionStudent->final_decision ?? $promotionStudent->recommendation;

            // Determine target section
            $targetSection = match ($decision) {
                'promote', 'probation' => $nextSection,
                'repeat' => $currentSection,
                'graduated' => null,
                default => $currentSection,
            };

            // Get next academic session
            $nextSession = AcademicSession::where('school_id', $this->batch->school_id)
                ->where('start_date', '>', $this->batch->academicSession->end_date)
                ->orderBy('start_date')
                ->first();

            if (!$nextSession && $decision !== 'graduated') {
                throw new \Exception("Next academic session not found for school {$this->batch->school_id}");
            }

            // Detach from current section (optional: keep history)
            if ($currentSection) {
                $student->classSections()->detach($currentSection->id);
            }

            // Attach to new section with new session
            if ($targetSection && $nextSession) {
                $student->classSections()->attach($targetSection->id, [
                    'academic_session_id' => $nextSession->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Create permanent history
            PromotionHistory::create([
                'student_id' => $student->id,
                'from_academic_session_id' => $this->batch->academic_session_id,
                'to_academic_session_id' => $nextSession?->id,
                'from_class_section_id' => $currentSection?->id,
                'to_class_section_id' => $targetSection?->id,
                'outcome' => $decision === 'probation' ? 'promoted' : $decision,
                'remarks' => $promotionStudent->override_reason,
                'executed_by' => auth()->id() ?? null,
                'executed_at' => now(),
            ]);

            // Mark as processed
            $promotionStudent->update([
                'is_processed' => true,
                'processed_at' => now(),
            ]);

            // Update batch progress
            $this->batch->increment('processed_students');

            Log::info('Student promoted successfully', [
                'student_id' => $student->id,
                'from' => $currentSection?->name,
                'to' => $targetSection?->name ?? 'graduated',
                'decision' => $decision,
                'batch_id' => $this->batch->id,
            ]);
        });
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessStudentPromotion job failed', [
            'batch_id' => $this->batch->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Optional: mark batch as failed
        if ($this->batch->exists) {
            $this->batch->update(['status' => 'failed']);
        }

        // Notify admin
        // Notification::send(...);
    }
}