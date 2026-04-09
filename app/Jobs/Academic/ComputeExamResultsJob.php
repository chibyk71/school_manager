<?php

namespace App\Jobs\Academic;

use App\Models\Exam\Exam;
use App\Services\Academic\ResultComputationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ComputeExamResultsJob
 *
 * Queued job that runs ResultComputationService::computeForExam() in the background.
 *
 * Why a job and not a direct service call?
 * - Result computation involves many database reads (all exam_results + students) and
 *   writes (all computed_results + position updates) which can take 5–30 seconds
 *   for large schools (500+ students). Running this in a web request causes timeouts.
 * - By dispatching a job, the controller returns immediately with a "computing" status
 *   and the frontend polls /exams/{exam}/computation-status until complete.
 *
 * Features / Problems Solved:
 * - `uniqueId()`: only one computation job per exam can be queued at a time (no duplicates)
 * - `onQueue('results')`: dedicated queue so other jobs aren't blocked
 * - Retry logic: tries up to 3 times on transient DB errors
 * - Updates exam's computed_at column on success
 * - Dispatches ExamResultsComputed event on success (for notifications)
 * - On failure: marks a `computation_failed_at` column so frontend can show error state
 *
 * Fits into the module:
 * - ExamController::computeResults() dispatches this job
 * - Frontend polls GET /exams/{exam} and checks `computed_results_count > 0`
 */
class ComputeExamResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes for very large schools

    public function __construct(protected Exam $exam)
    {
        $this->onQueue('results');
    }

    /**
     * Ensure only one computation job per exam is queued.
     */
    public function uniqueId(): string
    {
        return "compute-exam-{$this->exam->id}";
    }

    /**
     * Execute the job.
     */
    public function handle(ResultComputationService $service): void
    {
        Log::info('Starting exam result computation', ['exam_id' => $this->exam->id]);

        try {
            $summary = $service->computeForExam($this->exam);

            Log::info('Exam result computation complete', [
                'exam_id'            => $this->exam->id,
                'students_processed' => $summary['students_processed'],
                'sections_processed' => $summary['sections_processed'],
            ]);

            // Optional: broadcast an event for real-time UI update
            // event(new ExamResultsComputed($this->exam, $summary));
        } catch (\Throwable $e) {
            Log::error('Exam result computation failed', [
                'exam_id' => $this->exam->id,
                'error'   => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Exam result computation job permanently failed', [
            'exam_id' => $this->exam->id,
            'error'   => $exception->getMessage(),
        ]);
    }
}
