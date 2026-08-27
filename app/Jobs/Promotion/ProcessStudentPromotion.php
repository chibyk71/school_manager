<?php

namespace App\Jobs\Promotion;

use App\Events\Promotion\PromotionBatchCompleted;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassSection;
use App\Models\Promotion\PromotionBatch;
use App\Models\Promotion\PromotionHistory;
use App\Models\Promotion\PromotionStudent;
use App\Models\Student\Student;
use App\Models\Student\StudentSessionPlacement;
use App\Models\User;
use App\Services\Student\StudentPlacementService;
use App\Services\Student\StudentStatusService;
use App\States\Promotion\Completed;
use App\States\Promotion\Executing;
use Carbon\Carbon;
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
 * For each unprocessed PromotionStudent:
 *  - Applies promote / repeat / graduate against StudentSessionPlacement (+ student status)
 *  - Writes an immutable PromotionHistory row
 *  - Marks the promotion_student row processed
 *
 * Batch counters:
 *  - processed_students = successful applications only
 *  - failed_students    = per-student failures
 *  - metadata.completed_with_errors = true when any student failed
 *
 * Transitions Executing → Completed when the job finishes (even with partial failures).
 * Callers should treat completed_with_errors as requiring follow-up, not a clean close.
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

    public function handle(
        StudentPlacementService $placementService,
        StudentStatusService $statusService
    ): void {
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
        $fromSession = AcademicSession::query()->find($this->batch->academic_session_id);
        $nextSession = $fromSession ? $this->resolveNextSession($fromSession) : null;

        try {
            PromotionStudent::query()
                ->where('promotion_batch_id', $this->batch->id)
                ->where('is_processed', false)
                ->chunkById(100, function ($students) use (
                    &$processed,
                    &$failed,
                    $placementService,
                    $statusService,
                    $fromSession,
                    $nextSession
                ) {
                    foreach ($students as $studentRecord) {
                        $this->processSingleStudent(
                            $studentRecord,
                            $placementService,
                            $statusService,
                            $fromSession,
                            $nextSession,
                            $processed,
                            $failed
                        );
                    }
                });

            $this->batch->refresh();
            $this->batch->processed_students = $processed;
            $this->batch->failed_students = $failed;
            $metadata = $this->batch->metadata ?? [];
            $metadata['completed_with_errors'] = $failed > 0;
            $metadata['execution_finished_at'] = now()->toIso8601String();
            $this->batch->metadata = $metadata;
            $this->batch->save();
            $this->batch->status->transitionTo(Completed::class);

            event(new PromotionBatchCompleted($this->batch->fresh()));

            Log::info('Promotion batch execution completed', [
                'batch_id' => $this->batch->id,
                'processed' => $processed,
                'failed' => $failed,
                'completed_with_errors' => $failed > 0,
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

    protected function processSingleStudent(
        PromotionStudent $studentRecord,
        StudentPlacementService $placementService,
        StudentStatusService $statusService,
        ?AcademicSession $fromSession,
        ?AcademicSession $nextSession,
        int &$processed,
        int &$failed
    ): void {
        try {
            DB::transaction(function () use (
                $studentRecord,
                $placementService,
                $statusService,
                $fromSession,
                $nextSession,
                &$processed
            ) {
                $studentRecord->loadMissing(['promotionBatch', 'nextClassSection', 'currentClassSection']);
                $finalOutcome = $studentRecord->final_outcome;
                $student = Student::query()->findOrFail($studentRecord->student_id);
                $actor = $this->resolveActor();

                $toSessionId = null;
                $toSectionId = $studentRecord->next_class_section_id;

                match ($finalOutcome) {
                    'promote' => $this->applyPromote(
                        $student,
                        $studentRecord,
                        $placementService,
                        $fromSession,
                        $nextSession,
                        $toSessionId,
                        $toSectionId
                    ),
                    'repeat' => $this->applyRepeat(
                        $student,
                        $studentRecord,
                        $placementService,
                        $fromSession,
                        $nextSession,
                        $toSessionId,
                        $toSectionId
                    ),
                    'graduate' => $this->applyGraduate(
                        $student,
                        $studentRecord,
                        $statusService,
                        $actor
                    ),
                    default => throw new \RuntimeException("Unknown promotion outcome: {$finalOutcome}"),
                };

                PromotionHistory::create([
                    'school_id' => $studentRecord->promotionBatch->school_id,
                    'student_id' => $studentRecord->student_id,
                    'promotion_batch_id' => $studentRecord->promotion_batch_id,
                    'promotion_student_id' => $studentRecord->id,
                    'from_academic_session_id' => $studentRecord->promotionBatch->academic_session_id,
                    'to_academic_session_id' => $toSessionId,
                    'from_class_section_id' => $studentRecord->current_class_section_id,
                    'to_class_section_id' => $finalOutcome === 'graduate' ? null : $toSectionId,
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
                    'processing_error' => null,
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

    protected function applyPromote(
        Student $student,
        PromotionStudent $studentRecord,
        StudentPlacementService $placementService,
        ?AcademicSession $fromSession,
        ?AcademicSession $nextSession,
        ?string &$toSessionId,
        ?string &$toSectionId
    ): void {
        if (! $nextSession) {
            throw new \RuntimeException(
                'Cannot promote: no subsequent academic session exists for this school. Create the next session first.'
            );
        }

        $section = $studentRecord->nextClassSection
            ?? ($toSectionId ? ClassSection::query()->find($toSectionId) : null);

        if (! $section) {
            throw new \RuntimeException('Cannot promote: next class section is not set on the promotion student row.');
        }

        $this->stampFromPlacement($student, $fromSession, 'promoted');

        $placementService->placeInSession($student, [
            'academic_session_id' => $nextSession->id,
            'class_level_id' => $section->class_level_id,
            'class_section_id' => $section->id,
            'promotion_outcome' => 'promoted',
            'notes' => 'Promoted via batch '.$this->batch->id,
        ]);

        $toSessionId = $nextSession->id;
        $toSectionId = $section->id;
    }

    protected function applyRepeat(
        Student $student,
        PromotionStudent $studentRecord,
        StudentPlacementService $placementService,
        ?AcademicSession $fromSession,
        ?AcademicSession $nextSession,
        ?string &$toSessionId,
        ?string &$toSectionId
    ): void {
        if (! $nextSession) {
            throw new \RuntimeException(
                'Cannot record repeat: no subsequent academic session exists for this school. Create the next session first.'
            );
        }

        $section = $studentRecord->currentClassSection
            ?? ($studentRecord->current_class_section_id
                ? ClassSection::query()->find($studentRecord->current_class_section_id)
                : null);

        if (! $section) {
            throw new \RuntimeException('Cannot record repeat: current class section is missing.');
        }

        $this->stampFromPlacement($student, $fromSession, 'repeated');

        $placementService->placeInSession($student, [
            'academic_session_id' => $nextSession->id,
            'class_level_id' => $section->class_level_id,
            'class_section_id' => $section->id,
            'promotion_outcome' => 'repeated',
            'notes' => 'Repeated via batch '.$this->batch->id,
        ]);

        $toSessionId = $nextSession->id;
        $toSectionId = $section->id;
    }

    protected function applyGraduate(
        Student $student,
        PromotionStudent $studentRecord,
        StudentStatusService $statusService,
        ?User $actor
    ): void {
        if (! $actor) {
            throw new \RuntimeException('Cannot graduate: executed_by user is missing on the batch.');
        }

        $statusService->markGraduated($student, Carbon::now(), $actor);
    }

    protected function stampFromPlacement(
        Student $student,
        ?AcademicSession $fromSession,
        string $outcome
    ): void {
        if (! $fromSession) {
            return;
        }

        // Note: placements.promotion_batch_id is legacy bigint; batches use UUIDs — stamp outcome only.
        StudentSessionPlacement::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $fromSession->id)
            ->update([
                'promotion_outcome' => $outcome,
            ]);
    }

    protected function resolveNextSession(AcademicSession $fromSession): ?AcademicSession
    {
        return AcademicSession::query()
            ->where('school_id', $fromSession->school_id)
            ->where('start_date', '>', $fromSession->start_date)
            ->orderBy('start_date')
            ->first();
    }

    protected function resolveActor(): ?User
    {
        $id = $this->batch->executed_by;
        if (! $id) {
            return null;
        }

        return User::query()->find($id);
    }
}
