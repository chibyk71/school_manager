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
use App\Models\School;
use App\Services\Student\PlacementAllocationService;
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
        StudentStatusService $statusService,
        PlacementAllocationService $allocationService
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
                    $statusService,
                    $allocationService,
                    $fromSession,
                    $nextSession
                ) {
                    foreach ($students as $studentRecord) {
                        $this->processSingleStudent(
                            $studentRecord,
                            $statusService,
                            $allocationService,
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
        StudentStatusService $statusService,
        PlacementAllocationService $allocationService,
        ?AcademicSession $fromSession,
        ?AcademicSession $nextSession,
        int &$processed,
        int &$failed
    ): void {
        try {
            DB::transaction(function () use (
                $studentRecord,
                $statusService,
                $allocationService,
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
                        $student, $studentRecord, $allocationService, $fromSession, $nextSession, $actor, $toSessionId, $toSectionId
                    ),
                    'repeat' => $this->applyRepeat(
                        $student, $studentRecord, $allocationService, $fromSession, $nextSession, $actor, $toSessionId, $toSectionId
                    ),
                    'graduate' => $this->applyGraduate($student, $studentRecord, $statusService, $actor),
                    'incomplete' => throw new \RuntimeException(
                        'Student recommendation is incomplete (missing results or unmapped next section). Override to promote/repeat/graduate before execution.'
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
        PlacementAllocationService $allocationService,
        ?AcademicSession $fromSession,
        ?AcademicSession $nextSession,
        ?User $actor,
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

        if (! $actor) {
            throw new \RuntimeException('Cannot promote: executed_by user is missing on the batch.');
        }

        $school = School::query()->find($student->school_id);
        if (! $school) {
            throw new \RuntimeException('Cannot promote: student school is missing.');
        }

        // Always use Phase 5/6 allocation path (capacity + registration policy).
        // No silent bypass via legacy placeOrUpdateInSession.
        $allocationService->placeForPromotionOutcome(
            $student,
            $school,
            $nextSession->id,
            $section->class_level_id,
            $section->id,
            $actor,
            'promoted',
            [
                'notes' => 'Promoted via batch '.$this->batch->id,
                'capacity_override' => false,
            ]
        );

        $toSessionId = $nextSession->id;
        $toSectionId = $section->id;
    }

    protected function applyRepeat(
        Student $student,
        PromotionStudent $studentRecord,
        PlacementAllocationService $allocationService,
        ?AcademicSession $fromSession,
        ?AcademicSession $nextSession,
        ?User $actor,
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

        if (! $actor) {
            throw new \RuntimeException('Cannot record repeat: executed_by user is missing on the batch.');
        }

        $school = School::query()->find($student->school_id);
        if (! $school) {
            throw new \RuntimeException('Cannot record repeat: student school is missing.');
        }

        // Always use Phase 5/6 allocation path (capacity + registration policy).
        // No silent bypass via legacy placeOrUpdateInSession.
        $allocationService->placeForPromotionOutcome(
            $student,
            $school,
            $nextSession->id,
            $section->class_level_id,
            $section->id,
            $actor,
            'repeated',
            [
                'notes' => 'Repeated via batch '.$this->batch->id,
                'capacity_override' => false,
            ]
        );

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

    protected function stampFromPlacement(Student $student, ?AcademicSession $fromSession, string $outcome): void
    {
        if (! $fromSession) {
            return;
        }

        StudentSessionPlacement::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $fromSession->id)
            ->update(['promotion_outcome' => $outcome]);
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

        return $id ? User::query()->find($id) : null;
    }
}
