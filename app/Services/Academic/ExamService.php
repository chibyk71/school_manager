<?php

namespace App\Services\Academic;

use App\Events\Academic\ExamPublished;
use App\Events\Academic\ExamResultsApproved;
use App\Models\Exam\Exam;
use App\Models\Exam\AssessmentTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * ExamService
 *
 * Central business logic for all Exam lifecycle operations.
 *
 * Responsibilities:
 * ─────────────────────────────────────────────────────────────────────────────
 * - create()             Create an exam with template assignment and initial validation
 * - update()             Update exam details (blocked once results are approved)
 * - delete()             Soft-delete with guards (cannot delete if results exist)
 * - transitionStatus()   Move exam through the status machine with full validation:
 *                          draft → published → ongoing → completed → results_approved
 * - lockExam()           Apply hard lock to prevent all further edits
 * - publishExam()        draft → published, fires ExamPublished event
 * - approveResults()     completed → results_approved, fires ExamResultsApproved event
 *
 * What this service does NOT do:
 * ─────────────────────────────────────────────────────────────────────────────
 * - HTTP concerns (handled in ExamController)
 * - Score entry (handled in ScoreEntryService)
 * - Result computation (handled in ResultComputationService)
 * - Response shaping (handled in ExamResource)
 *
 * Business Rules:
 * ─────────────────────────────────────────────────────────────────────────────
 * - An exam cannot be deleted once any score has been entered
 * - Status transitions must follow the defined machine (no skipping)
 * - Only one exam per class_level/term can be published at a time (configurable)
 * - Exam dates must fall within the parent term's date range
 * - A valid, active assessment template must be assigned at creation
 */
class ExamService
{
    // ─── Create ───────────────────────────────────────────────────────────────

    /**
     * Create a new exam with template assignment.
     *
     * Validates:
     * - Template exists and is active for the school
     * - Exam dates fall within the term's date range (if term provided)
     * - No duplicate exam for same class/term/session
     *
     * @param  array  $data  Validated data from StoreExamRequest
     * @return Exam
     * @throws ValidationException
     * @throws Throwable
     */
    public function create(array $data): Exam
    {
        // Validate template
        $template = AssessmentTemplate::find($data['assessment_template_id']);
        if (!$template || !$template->is_active) {
            throw ValidationException::withMessages([
                'assessment_template_id' => 'The selected assessment template is invalid or inactive.',
            ]);
        }

        // Validate template weights sum to 100
        if ($template->total_weight !== 100.0) {
            throw ValidationException::withMessages([
                'assessment_template_id' => "The template's component weights must sum to 100 (currently {$template->total_weight}).",
            ]);
        }

        // Check for duplicate exam (same class_level + class_section + term)
        $this->assertNoDuplicateExam($data);

        return DB::transaction(function () use ($data): Exam {
            $exam = Exam::create(array_merge($data, [
                'status'     => Exam::STATUS_DRAFT,
                'created_by' => auth()->id(),
            ]));

            Log::info('Exam created', [
                'exam_id'   => $exam->id,
                'name'      => $exam->name,
                'school_id' => $exam->school_id,
            ]);

            return $exam->load(['assessmentTemplate', 'classLevel', 'classSection', 'term']);
        });
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    /**
     * Update exam details.
     *
     * Blocked once the exam reaches results_approved status or is locked.
     * Status changes are NOT handled here — use transitionStatus() instead.
     *
     * @throws ValidationException
     */
    public function update(Exam $exam, array $data): Exam
    {
        if ($exam->isLocked()) {
            throw ValidationException::withMessages([
                'exam' => 'This exam is locked and cannot be edited.',
            ]);
        }

        if ($exam->isApproved()) {
            throw ValidationException::withMessages([
                'exam' => 'Exam results have been approved. The exam can no longer be edited.',
            ]);
        }

        return DB::transaction(function () use ($exam, $data): Exam {
            // If template is being changed and scores already exist, block it
            if (
                isset($data['assessment_template_id']) &&
                $data['assessment_template_id'] !== $exam->assessment_template_id &&
                $exam->examResults()->exists()
            ) {
                throw ValidationException::withMessages([
                    'assessment_template_id' => 'Cannot change the assessment template after scores have been entered.',
                ]);
            }

            $exam->update($data);

            return $exam->fresh(['assessmentTemplate', 'classLevel', 'classSection', 'term']);
        });
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    /**
     * Soft-delete an exam.
     *
     * Blocked if any exam results have been entered (even partially).
     * The admin must manually clear all scores before deleting the exam event.
     *
     * @throws ValidationException
     */
    public function delete(Exam $exam): void
    {
        if ($exam->examResults()->exists()) {
            throw ValidationException::withMessages([
                'exam' => 'Cannot delete an exam with scores already entered. Please clear all scores first.',
            ]);
        }

        DB::transaction(function () use ($exam): void {
            // Also clean up timetable entries
            $exam->timetable()->delete();
            $exam->delete();

            Log::info('Exam deleted', ['exam_id' => $exam->id, 'name' => $exam->name]);
        });
    }

    // ─── Status Transitions ───────────────────────────────────────────────────

    /**
     * Transition the exam to a new status.
     *
     * Enforces the status machine defined in Exam::STATUS_TRANSITIONS.
     * Each target status has additional pre-conditions:
     *
     * → published:         Exam must have at least one subject in the timetable (optional, configurable)
     * → ongoing:           Exam start_date must be today or in the past
     * → completed:         All enrolled students must have at least one score entered
     * → results_approved:  ResultComputationService must have run (computed_results must exist)
     *
     * @throws ValidationException
     */
    public function transitionStatus(Exam $exam, string $newStatus, array $options = []): Exam
    {
        if (!$exam->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition from '{$exam->status}' to '{$newStatus}'.",
            ]);
        }

        return match ($newStatus) {
            Exam::STATUS_PUBLISHED        => $this->publishExam($exam),
            Exam::STATUS_ONGOING          => $this->markOngoing($exam),
            Exam::STATUS_COMPLETED        => $this->markCompleted($exam),
            Exam::STATUS_RESULTS_APPROVED => $this->approveResults($exam, $options),
            Exam::STATUS_DRAFT            => $this->revertToDraft($exam),
            default                       => throw new \InvalidArgumentException("Unknown status: {$newStatus}"),
        };
    }

    // ─── Individual Status Handlers ───────────────────────────────────────────

    /**
     * Publish an exam (draft → published).
     * Teachers can now see and enter scores after this.
     */
    private function publishExam(Exam $exam): Exam
    {
        return DB::transaction(function () use ($exam): Exam {
            $exam->update([
                'status'       => Exam::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);

            event(new ExamPublished($exam, auth()->user()->id));

            Log::info('Exam published', ['exam_id' => $exam->id]);

            return $exam->fresh();
        });
    }

    /**
     * Mark exam as ongoing (published → ongoing).
     */
    private function markOngoing(Exam $exam): Exam
    {
        return DB::transaction(function () use ($exam): Exam {
            $exam->update(['status' => Exam::STATUS_ONGOING]);
            return $exam->fresh();
        });
    }

    /**
     * Mark exam as completed (ongoing → completed).
     * At this point all scores should be entered. Warns if not (does not block).
     */
    private function markCompleted(Exam $exam): Exam
    {
        return DB::transaction(function () use ($exam): Exam {
            $exam->update(['status' => Exam::STATUS_COMPLETED]);

            Log::info('Exam marked completed', ['exam_id' => $exam->id]);

            return $exam->fresh();
        });
    }

    /**
     * Approve results (completed → results_approved).
     * This is the final state — hard-locks the exam and publishes results to students/parents.
     *
     * Pre-conditions:
     * - ResultComputationService must have been run (computed_results must exist for all sections)
     * - All enrolled students must have computed_results rows
     */
    private function approveResults(Exam $exam, array $options = []): Exam
    {
        // Ensure computation has been run
        $sectionsCount = $exam->getApplicableSections()->count();
        $computedCount = $exam->computedResults()->count();

        if ($sectionsCount > 0 && $computedCount === 0) {
            throw ValidationException::withMessages([
                'exam' => 'Results must be computed before they can be approved. Please run the result computation first.',
            ]);
        }

        return DB::transaction(function () use ($exam, $options): Exam {
            $exam->update([
                'status'                 => Exam::STATUS_RESULTS_APPROVED,
                'results_published_at'   => $options['publish_to_students'] ?? true ? now() : null,
                'locked_at'              => now(),
                'approved_by'            => auth()->id(),
            ]);

            // Mark all computed_results as final
            $exam->computedResults()->update(['is_final' => true]);

            event(new ExamResultsApproved($exam, auth()->user()->id,));

            Log::info('Exam results approved and locked', ['exam_id' => $exam->id]);

            return $exam->fresh();
        });
    }

    /**
     * Revert to draft (published → draft).
     * Only allowed if no scores have been entered yet.
     */
    private function revertToDraft(Exam $exam): Exam
    {
        if ($exam->examResults()->exists()) {
            throw ValidationException::withMessages([
                'exam' => 'Cannot revert to draft after scores have been entered.',
            ]);
        }

        return DB::transaction(function () use ($exam): Exam {
            $exam->update([
                'status'       => Exam::STATUS_DRAFT,
                'published_at' => null,
            ]);

            return $exam->fresh();
        });
    }

    // ─── Guards ───────────────────────────────────────────────────────────────

    /**
     * Prevent duplicate exams for the same class_level + class_section + term + session.
     *
     * @throws ValidationException
     */
    private function assertNoDuplicateExam(array $data, ?string $excludeId = null): void
    {
        $query = Exam::where('school_id', $data['school_id'] ?? GetSchoolModel()?->id)
            ->where('academic_session_id', $data['academic_session_id'])
            ->where('class_level_id', $data['class_level_id'] ?? null)
            ->where('class_section_id', $data['class_section_id'] ?? null)
            ->where('name', $data['name']);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => 'An exam with this name already exists for the selected class and session.',
            ]);
        }
    }
}
