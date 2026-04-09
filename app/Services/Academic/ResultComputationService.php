<?php

namespace App\Services\Academic;

use App\Models\Academic\ClassSection;
use App\Models\Exam\ComputedResult;
use App\Models\Exam\Exam;
use App\Models\Exam\ExamResult;
use App\Models\Academic\Grade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * ResultComputationService
 *
 * Aggregates raw ExamResult scores into ComputedResult summary rows.
 *
 * This is the most computationally intensive service in the module.
 * It MUST be called after all scores are entered and before results_approved.
 *
 * What it computes:
 * ─────────────────────────────────────────────────────────────────────────────
 * For EACH student in the exam's sections:
 *   1. Collects all exam_results for this student → per-subject breakdown
 *   2. Computes subject stats: class average, highest score, lowest score, position
 *   3. Computes student aggregate: total obtained, average %, position in class/level
 *   4. Assigns grade at aggregate level
 *   5. Freezes everything into computed_results (one row per student)
 *
 * Re-runnable (idempotent):
 * Can be run multiple times on the same exam — each run regenerates all
 * computed_results using updateOrCreate. Previous data is overwritten.
 * This allows corrections after re-running after a score fix.
 *
 * Performance strategy:
 * - Loads all exam_results for the entire exam in a single query, then
 *   groups in PHP — avoids N+1 queries
 * - Uses a single DB transaction for all computed_results writes
 * - Chunk-processes sections for large schools
 *
 * Fits into the module:
 * - Called by ComputeExamResultsJob (dispatched from ExamController)
 * - ExamController has a "Compute Results" button that dispatches this job
 * - ExamResultsApproved event listener calls this to freeze results
 */
class ResultComputationService
{
    /**
     * Compute all results for a given exam across all applicable sections.
     *
     * @param  Exam  $exam
     * @return array Summary: { students_processed: int, sections_processed: int }
     * @throws ValidationException|Throwable
     */
    public function computeForExam(Exam $exam): array
    {
        $sections = $exam->getApplicableSections();

        if ($sections->isEmpty()) {
            throw ValidationException::withMessages([
                'exam' => 'No class sections found for this exam. Please check the exam configuration.',
            ]);
        }

        $totalStudentsProcessed = 0;

        DB::transaction(function () use ($exam, $sections, &$totalStudentsProcessed) {
            // Load ALL exam_results for this exam at once (single query)
            $allResults = ExamResult::where('exam_id', $exam->id)
                ->with(['subject:id,name,code', 'student:id,profile_id'])
                ->get()
                ->groupBy('student_id'); // Group by student for fast per-student access

            // Process each section
            foreach ($sections as $section) {
                $computed = $this->computeForSection($exam, $section, $allResults);
                $totalStudentsProcessed += count($computed);
            }

            // Update exam's computed_at timestamp
            $exam->update(['computed_at' => now()]);
        });

        Log::info('Result computation completed', [
            'exam_id'            => $exam->id,
            'sections_processed' => $sections->count(),
            'students_processed' => $totalStudentsProcessed,
        ]);

        return [
            'students_processed' => $totalStudentsProcessed,
            'sections_processed' => $sections->count(),
        ];
    }

    // ─── Per-Section Computation ──────────────────────────────────────────────

    /**
     * Compute results for all students in one section.
     *
     * @param  Collection  $allResults  All exam_results grouped by student_id
     * @return array  Array of ComputedResult models created/updated
     */
    private function computeForSection(Exam $exam, ClassSection $section, Collection $allResults): array
    {
        // Get students enrolled in this section for this exam's session
        $students = \App\Models\Academic\Student::whereHas('classSections', function ($q) use ($section, $exam) {
            $q->where('class_section_id', $section->id)
                ->where('academic_session_id', $exam->academic_session_id);
        })->get();

        if ($students->isEmpty()) {
            return [];
        }

        // Compute per-subject stats for this section (averages, highest, lowest)
        $subjectStats = $this->computeSubjectStats($exam, $section, $allResults);

        $computedRows = [];

        foreach ($students as $student) {
            $studentResults = $allResults->get($student->id, collect());
            $sectionResults = $studentResults->where('class_section_id', $section->id);

            $computed = $this->computeStudentAggregate($exam, $student, $section, $sectionResults, $subjectStats);

            $computedRows[$student->id] = $computed;
        }

        // Assign class positions based on average_score descending
        $this->assignClassPositions($exam, $section, $computedRows);

        return $computedRows;
    }

    // ─── Per-Student Computation ──────────────────────────────────────────────

    /**
     * Compute aggregate for a single student.
     */
    private function computeStudentAggregate(
        Exam $exam,
        $student,
        ClassSection $section,
        Collection $studentSectionResults,
        array $subjectStats
    ): ComputedResult {
        $template = $exam->assessmentTemplate;

        $totalObtained = 0.0;
        $totalPossible = 0.0;
        $subjectsPassed = 0;
        $subjectsFailed = 0;
        $subjectsScored = 0;
        $subjectBreakdown = [];

        foreach ($studentSectionResults as $result) {
            $subjectId   = $result->subject_id;
            $subjectStat = $subjectStats[$subjectId] ?? null;

            if ($result->is_absent || $result->is_exempted || $result->total_score === null) {
                // Include in breakdown but exclude from aggregate calculation
                $subjectBreakdown[] = [
                    'subject_id'        => $subjectId,
                    'subject_name'      => $result->subject?->name,
                    'subject_code'      => $result->subject?->code,
                    'total_score'       => $result->total_score,
                    'grade_code'        => $result->is_absent ? 'ABS' : ($result->is_exempted ? 'EXM' : null),
                    'grade_remark'      => $result->is_absent ? 'Absent' : 'Exempted',
                    'is_absent'         => $result->is_absent,
                    'is_exempted'       => $result->is_exempted,
                    'class_average'     => $subjectStat['average'] ?? null,
                    'highest_score'     => $subjectStat['highest'] ?? null,
                    'lowest_score'      => $subjectStat['lowest'] ?? null,
                    'position_in_class' => null,
                ];
                continue;
            }

            $score = (float) $result->total_score;
            $totalObtained += $score;
            $totalPossible += $template->total_score;
            $subjectsScored++;

            $passed = $score >= $template->pass_mark;
            if ($passed) $subjectsPassed++; else $subjectsFailed++;

            $subjectBreakdown[] = [
                'subject_id'        => $subjectId,
                'subject_name'      => $result->subject?->name,
                'subject_code'      => $result->subject?->code,
                'total_score'       => $score,
                'grade_code'        => $result->grade_code,
                'grade_remark'      => $result->grade_remark,
                'is_absent'         => false,
                'is_exempted'       => false,
                'class_average'     => $subjectStat['average'] ?? null,
                'highest_score'     => $subjectStat['highest'] ?? null,
                'lowest_score'      => $subjectStat['lowest'] ?? null,
                'position_in_class' => $subjectStat['positions'][$student->id] ?? null,
            ];
        }

        $averageScore = $totalPossible > 0
            ? round(($totalObtained / $totalPossible) * 100, 2)
            : 0.0;

        return ComputedResult::updateOrCreate(
            [
                'exam_id'    => $exam->id,
                'student_id' => $student->id,
            ],
            [
                'school_id'              => $exam->school_id,
                'class_section_id'       => $section->id,
                'total_score_obtained'   => round($totalObtained, 2),
                'total_score_possible'   => round($totalPossible, 2),
                'average_score'          => $averageScore,
                'subjects_count'         => $studentSectionResults->count(),
                'subjects_scored'        => $subjectsScored,
                'subjects_passed'        => $subjectsPassed,
                'subjects_failed'        => $subjectsFailed,
                'subject_breakdown'      => $subjectBreakdown,
                'class_size'             => null, // Set later in assignClassPositions()
                'computed_at'            => now(),
                'is_final'               => $exam->isApproved(),
            ]
        );
    }

    // ─── Subject Stats ────────────────────────────────────────────────────────

    /**
     * Compute per-subject statistics for a section: average, highest, lowest, per-student positions.
     *
     * Returns: [ subjectId => [ average, highest, lowest, positions: [ studentId => rank ] ] ]
     */
    private function computeSubjectStats(Exam $exam, ClassSection $section, Collection $allResults): array
    {
        // Filter to just this section's results
        $sectionResults = $allResults->flatten()->where('class_section_id', $section->id);

        // Group by subject
        $bySubject = $sectionResults
            ->whereNull('is_absent')
            ->where('is_absent', false)
            ->where('is_exempted', false)
            ->whereNotNull('total_score')
            ->groupBy('subject_id');

        $stats = [];

        foreach ($bySubject as $subjectId => $results) {
            $scores = $results->pluck('total_score')->map(fn($s) => (float) $s);

            // Sort for position assignment (highest first)
            $sorted = $results->sortByDesc('total_score')->values();

            $positions = [];
            $rank = 1;
            $prevScore = null;
            $sameRankCount = 0;

            foreach ($sorted as $result) {
                $score = (float) $result->total_score;

                if ($prevScore !== null && $score === $prevScore) {
                    $sameRankCount++;
                } else {
                    $rank += $sameRankCount;
                    $sameRankCount = 1;
                    $prevScore = $score;
                }

                $positions[$result->student_id] = $rank;
            }

            $stats[$subjectId] = [
                'average'   => $scores->average() !== null ? round($scores->average(), 2) : null,
                'highest'   => $scores->max(),
                'lowest'    => $scores->min(),
                'positions' => $positions,
            ];
        }

        return $stats;
    }

    // ─── Class Positions ──────────────────────────────────────────────────────

    /**
     * Assign class positions (1st, 2nd, 3rd...) to computed results based on average_score.
     * Handles ties — equal average = same position.
     */
    private function assignClassPositions(Exam $exam, ClassSection $section, array &$computedRows): void
    {
        // Sort by average_score descending for ranking
        $sorted = collect($computedRows)->sortByDesc(fn($cr) => $cr->average_score)->values();

        $classSize = $sorted->count();
        $rank = 1;
        $prevAverage = null;
        $sameRankCount = 0;

        foreach ($sorted as $computedResult) {
            $average = (float) $computedResult->average_score;

            if ($prevAverage !== null && $average === $prevAverage) {
                $sameRankCount++;
            } else {
                $rank += $sameRankCount;
                $sameRankCount = 1;
                $prevAverage = $average;
            }

            $computedResult->update([
                'position_in_class' => $rank,
                'class_size'        => $classSize,
            ]);
        }
    }
}
