<?php

namespace App\Listeners\Academic;

use App\Events\Academic\GradeDeleted;
use App\Events\Academic\GradeUpdated;
use App\Jobs\Academic\RecalculateExamResultsForGrade;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Listener: RecalculateResultsOnGradeChange
 *
 * Reacts to GradeUpdated and GradeDeleted events.
 * When a grade is modified or removed, this listener queues a background job
 * to recalculate all affected ExamResults (and potentially GPAs, class averages, etc.).
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Maintains data consistency after retroactive grading scale changes
 *   (e.g. raising min_score from 70→75 should update students who previously got B to C)
 * • Prevents blocking the main request thread → uses queued job for heavy computation
 * • Only triggers recalculation if the grade is actually in use (via model's isUsed())
 * • Handles both update (re-grade with new ranges) and delete (nullify or fallback grade)
 * • Structured logging for monitoring & debugging in production
 * • Implements ShouldQueue → can be processed asynchronously via Laravel queue workers
 * • Safe & idempotent: job can be retried without duplicating work
 *
 * How it fits into the Grades Module:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Registered in EventServiceProvider (or discovered automatically)
 * • Triggered by events dispatched from GradeService (update/delete methods)
 * • Works in tandem with:
 *   - Grade model (isUsed() helper)
 *   - RecalculateExamResultsForGrade job (actual recalculation logic)
 *   - Spatie Activitylog (already captures who/what changed)
 * • Ensures that UI (report cards, transcripts, dashboards) always shows correct grades
 * • Critical for schools that frequently adjust grading policies mid-term
 *
 * Configuration Notes:
 * • Queue name: 'academic' (recommended – create in config/queue.php if needed)
 * • Retry attempts: configurable via job (default 3–5)
 * • Monitoring: use Laravel Horizon, Telescope, or failed_jobs table
 *
 * EventServiceProvider mapping example:
 * protected $listen = [
 *     GradeUpdated::class => [RecalculateResultsOnGradeChange::class],
 *     GradeDeleted::class => [RecalculateResultsOnGradeChange::class],
 * ];
 */
class RecalculateResultsOnGradeChange implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job should run.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes – adjust based on expected load

    /**
     * Handle the event.
     *
     * @param  GradeUpdated|GradeDeleted  $event
     * @return void
     */
    public function handle(GradeUpdated|GradeDeleted $event): void
    {
        $grade = $event->grade;

        // Early return if this grade is not used anywhere → no need to recalculate
        if (!$grade->isUsed()) {
            Log::info('No recalculation needed - grade is not referenced in any results', [
                'grade_id' => $grade->id,
                'event' => class_basename($event),
            ]);
            return;
        }

        try {
            // Dispatch the heavy-lifting job to the queue
            RecalculateExamResultsForGrade::dispatch($grade)
                ->onQueue('academic')
                ->delay(now()->addSeconds(10)); // small delay to batch if multiple changes happen

            Log::info('Queued recalculation job for affected results', [
                'grade_id' => $grade->id,
                'event' => class_basename($event),
                'is_used' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue grade recalculation job', [
                'grade_id' => $grade->id,
                'event' => class_basename($event),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Optional: notify admin / Sentry / etc.
            report($e);
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  GradeUpdated|GradeDeleted  $event
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(GradeUpdated|GradeDeleted $event, \Throwable $exception): void
    {
        Log::critical('Grade recalculation job failed after retries', [
            'grade_id' => $event->grade->id,
            'event' => class_basename($event),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // TODO: send emergency notification to admin
        Notification::route('mail', 'admin@school.com')
            ->notify(new GradeRecalculationFailed($event->grade, $exception));
    }
}
