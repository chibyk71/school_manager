<?php

namespace App\Services;

use App\Events\Promotion\PromotionBatchApproved;
use App\Events\Promotion\PromotionBatchCancelled;
use App\Events\Promotion\PromotionBatchCreated;
use App\Events\Promotion\StudentDecisionOverridden;
use App\Jobs\Promotion\PopulatePromotionBatch;
use App\Jobs\Promotion\ProcessStudentPromotion;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use App\Models\Exam\ExamResult;
use App\Models\Misc\AttendanceLedger;
use App\Models\Promotion\PromotionBatch;
use App\Models\Promotion\PromotionStudent;
use App\Models\Student\Student;
use App\Models\Student\StudentSessionPlacement;
use App\Models\User;
use App\States\Promotion\Approved;
use App\States\Promotion\Cancelled;
use App\States\Promotion\Draft;
use App\States\Promotion\Executing;
use App\States\Promotion\Pending;
use App\States\Promotion\Reviewing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public function createBatchForSession(
        AcademicSession $session,
        ?User $initiator = null,
        ?string $name = null,
        ?string $description = null
    ): PromotionBatch {
        $schoolId = $session->school_id;

        $existing = PromotionBatch::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'academic_session_id' => 'A promotion batch already exists for this academic session.',
            ]);
        }

        $batch = DB::transaction(function () use ($session, $schoolId, $initiator, $name, $description) {
            return PromotionBatch::create([
                'school_id' => $schoolId,
                'academic_session_id' => $session->id,
                'name' => $name ?: ('Promotion – ' . $session->name),
                'description' => $description,
                'status' => Draft::class,
                'initiated_by' => $initiator?->id,
                'total_students' => 0,
                'processed_students' => 0,
                'failed_students' => 0,
            ]);
        });

        PopulatePromotionBatch::dispatch($batch);
        event(new PromotionBatchCreated($batch));

        Log::info('Promotion batch created', [
            'batch_id' => $batch->id,
            'session_id' => $session->id,
            'school_id' => $schoolId,
        ]);

        return $batch->fresh();
    }

    public function populateBatch(PromotionBatch $batch): void
    {
        if (! $batch->status->equals(Draft::class)) {
            Log::info('Populate skipped; batch not draft', [
                'batch_id' => $batch->id,
                'status' => (string) $batch->status,
            ]);
            return;
        }

        $session = $batch->academicSession;

        $placements = StudentSessionPlacement::query()
            ->where('academic_session_id', $session->id)
            ->whereHas('student', function ($q) use ($batch) {
                $q->where('school_id', $batch->school_id)
                    ->whereIn('status', ['active', 'enrolled', 'admitted']);
            })
            ->with(['student', 'classSection.classLevel'])
            ->get();

        $rows = [];

        foreach ($placements as $placement) {
            $student = $placement->student;
            if (! $student) {
                continue;
            }

            $computed = $this->computeStudentRecommendation($student, $session, $placement);

            $rows[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'promotion_batch_id' => $batch->id,
                'student_id' => $student->id,
                'current_class_section_id' => $computed['current_class_section_id'],
                'next_class_section_id' => $computed['next_class_section_id'],
                'recommendation' => $computed['recommendation'],
                'average_score' => $computed['average_score'],
                'failed_subjects_count' => $computed['failed_subjects_count'],
                'total_subjects_count' => $computed['total_subjects_count'],
                'attendance_percentage' => $computed['attendance_percentage'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($batch, $rows) {
            if ($rows !== []) {
                foreach (array_chunk($rows, 200) as $chunk) {
                    PromotionStudent::insert($chunk);
                }
            }

            $batch->total_students = count($rows);
            $batch->save();
            $batch->status->transitionTo(Pending::class);
        });

        Log::info('Promotion batch populated', [
            'batch_id' => $batch->id,
            'total_students' => count($rows),
        ]);
    }

    public function computeStudentRecommendation(
        Student $student,
        AcademicSession $session,
        ?StudentSessionPlacement $placement = null
    ): array {
        $placement ??= $student->sessionPlacements()
            ->where('academic_session_id', $session->id)
            ->with('classSection.classLevel')
            ->first();

        $currentSection = $placement?->classSection;
        $currentLevel = $currentSection?->classLevel;

        $school = $student->school ?? GetSchoolModel();
        $promotionRules = getMergedSettings('academic.promotion_rules', $school) ?? [];
        $attendanceRules = getMergedSettings('academic.attendance_rules', $school) ?? [];

        $failThreshold = (int) ($promotionRules['fail_subject_threshold'] ?? 3);
        $passAverage = (float) ($promotionRules['pass_average'] ?? 40);
        $useAttendance = (bool) ($attendanceRules['use_attendance_for_promotion'] ?? false);
        $minAttendance = (float) ($attendanceRules['promotion_min_attendance_percent'] ?? 75);

        $results = ExamResult::query()
            ->where('student_id', $student->id)
            ->where('is_exempted', false)
            ->whereNotNull('total_score')
            ->whereHas('exam', function ($q) use ($session) {
                $q->where('academic_session_id', $session->id)
                    ->whereIn('status', ['completed', 'results_approved', 'published', 'ongoing']);
            })
            ->with(['exam.assessmentTemplate'])
            ->get();

        $bySubject = $results->groupBy('subject_id')->map(function ($group) {
            return $group->sortByDesc(fn (ExamResult $r) => $r->created_at)->first();
        });

        $totalSubjects = $bySubject->count();
        $averageScore = $totalSubjects > 0
            ? round((float) $bySubject->avg(fn (ExamResult $r) => (float) $r->total_score), 2)
            : null;

        $failed = 0;
        foreach ($bySubject as $result) {
            $passMark = $result->exam?->assessmentTemplate?->pass_mark ?? $passAverage;
            if ((float) $result->total_score < (float) $passMark) {
                $failed++;
            }
        }

        $attendancePct = null;
        if ($useAttendance) {
            $attendancePct = $this->computeAttendancePercentage($student, $session);
        }

        $nextSectionId = null;
        $hasNextLevel = false;
        if ($currentLevel && $currentSection) {
            $hasNextLevel = $this->hasNextClassLevel($currentLevel);
            if ($hasNextLevel) {
                $nextSectionId = $this->resolveNextClassSection($currentSection)?->id;
            }
        }

        $recommendation = 'promote';
        if ($totalSubjects === 0) {
            $recommendation = 'repeat';
        } elseif ($failed >= $failThreshold) {
            $recommendation = 'repeat';
        } elseif ($averageScore !== null && $averageScore < $passAverage) {
            $recommendation = 'repeat';
        } elseif ($useAttendance && $attendancePct !== null && $attendancePct < $minAttendance) {
            $recommendation = 'repeat';
        } elseif (! $hasNextLevel) {
            $recommendation = 'graduate';
        }

        return [
            'recommendation' => $recommendation,
            'average_score' => $averageScore,
            'failed_subjects_count' => $failed,
            'total_subjects_count' => $totalSubjects,
            'attendance_percentage' => $attendancePct,
            'current_class_section_id' => $currentSection?->id,
            'next_class_section_id' => $recommendation === 'repeat' ? $currentSection?->id : $nextSectionId,
        ];
    }

    public function overrideStudentDecision(
        PromotionStudent $promotionStudent,
        string $finalDecision,
        ?string $reason,
        User $actor
    ): PromotionStudent {
        $batch = $promotionStudent->promotionBatch;

        if (! $batch->status->canOverrideStudents()) {
            throw ValidationException::withMessages([
                'status' => 'Student decisions can only be overridden while the batch is pending or under review.',
            ]);
        }

        if (! in_array($finalDecision, ['promote', 'repeat', 'graduate'], true)) {
            throw ValidationException::withMessages([
                'final_decision' => 'Invalid promotion outcome.',
            ]);
        }

        $old = $promotionStudent->recommendation;

        $promotionStudent->update([
            'final_decision' => $finalDecision,
            'override_reason' => $reason,
            'overridden_by' => $actor->id,
            'overridden_at' => now(),
        ]);

        if ($batch->status->equals(Pending::class)) {
            $batch->status->transitionTo(Reviewing::class);
        }

        event(new StudentDecisionOverridden(
            $promotionStudent->fresh(),
            $actor,
            $old,
            $finalDecision,
            $reason
        ));

        return $promotionStudent->fresh();
    }

    public function approveBatch(PromotionBatch $batch, User $actor, ?string $comments = null): PromotionBatch
    {
        if (! $batch->status->canBeApproved()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending or reviewing batches can be approved.',
            ]);
        }

        $batch->approved_by = $actor->id;
        $batch->approved_at = now();
        $batch->approval_comments = $comments;
        $batch->save();
        $batch->status->transitionTo(Approved::class);
        event(new PromotionBatchApproved($batch->fresh()));

        return $batch->fresh();
    }

    public function sendBackForReview(PromotionBatch $batch): PromotionBatch
    {
        if (! $batch->status->equals(Reviewing::class)) {
            throw ValidationException::withMessages([
                'status' => 'Only reviewing batches can be sent back to pending.',
            ]);
        }

        $batch->status->transitionTo(Pending::class);

        return $batch->fresh();
    }

    public function executeBatch(PromotionBatch $batch, User $actor): PromotionBatch
    {
        if (! $batch->status->canBeExecuted()) {
            throw ValidationException::withMessages([
                'status' => 'Only approved batches can be executed.',
            ]);
        }

        $batch->executed_by = $actor->id;
        $batch->executed_at = now();
        $batch->save();
        $batch->status->transitionTo(Executing::class);
        ProcessStudentPromotion::dispatch($batch->fresh());

        return $batch->fresh();
    }

    public function cancelBatch(PromotionBatch $batch, User $actor, ?string $reason = null): PromotionBatch
    {
        if (! $batch->status->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'This batch cannot be cancelled in its current state.',
            ]);
        }

        $metadata = $batch->metadata ?? [];
        $metadata['cancellation'] = [
            'reason' => $reason,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now()->toIso8601String(),
        ];
        $batch->metadata = $metadata;
        $batch->save();
        $batch->status->transitionTo(Cancelled::class);
        event(new PromotionBatchCancelled($batch->fresh()));

        return $batch->fresh();
    }

    protected function computeAttendancePercentage(Student $student, AcademicSession $session): ?float
    {
        $ledgers = AttendanceLedger::query()
            ->where('attendable_type', Student::class)
            ->where('attendable_id', $student->id)
            ->whereHas('attendanceSession', function ($q) use ($session) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('attendance_sessions', 'academic_session_id')) {
                    $q->where('academic_session_id', $session->id);
                }
            })
            ->get();

        if ($ledgers->isEmpty()) {
            return null;
        }

        $countable = $ledgers->filter(fn ($l) => ! in_array($l->status, ['holiday', 'leave'], true));
        if ($countable->isEmpty()) {
            return null;
        }

        $present = $countable->filter(fn ($l) => in_array($l->status, ['present', 'late'], true))->count();

        return round(($present / $countable->count()) * 100, 2);
    }

    protected function hasNextClassLevel(ClassLevel $currentLevel): bool
    {
        return ClassLevel::query()
            ->where('school_section_id', $currentLevel->school_section_id)
            ->where('is_active', true)
            ->where('sequence', '>', $currentLevel->sequence)
            ->exists();
    }

    protected function resolveNextClassSection(ClassSection $currentSection): ?ClassSection
    {
        $currentLevel = $currentSection->classLevel;
        if (! $currentLevel) {
            return null;
        }

        $nextLevel = ClassLevel::query()
            ->where('school_section_id', $currentLevel->school_section_id)
            ->where('is_active', true)
            ->where('sequence', '>', $currentLevel->sequence)
            ->orderBy('sequence')
            ->first();

        if (! $nextLevel) {
            return null;
        }

        $sameArm = ClassSection::query()
            ->where('class_level_id', $nextLevel->id)
            ->where('is_active', true)
            ->when($currentSection->name, fn ($q) => $q->where('name', $currentSection->name))
            ->first();

        if ($sameArm) {
            return $sameArm;
        }

        return ClassSection::query()
            ->where('class_level_id', $nextLevel->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->first();
    }
}
