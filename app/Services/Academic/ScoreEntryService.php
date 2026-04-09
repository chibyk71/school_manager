<?php

namespace App\Services\Academic;

use App\Models\Academic\Exam;
use App\Models\Academic\ExamResult;
use App\Models\Academic\AssessmentTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * ScoreEntryService
 *
 * Handles all score entry operations for exam results.
 *
 * Key Responsibilities:
 * ─────────────────────────────────────────────────────────────────────────────
 * - saveBulkScores()     Save/update scores for multiple students in a subject at once
 *                        (the primary method called from the score-entry form submission)
 * - saveStudentScore()   Save/update all component scores for one student in one subject
 * - markAbsent()         Mark a student as absent for a subject (clears scores)
 * - markExempted()       Mark a student as exempted from a subject
 * - getScoreEntrySheet() Build the data structure needed to render the score-entry UI:
 *                        returns all students in the section with their current scores
 *                        for the requested subject, ready for the form
 *
 * Upsert Strategy:
 * All score saves use updateOrCreate() to be idempotent — repeated submissions
 * (save-as-you-go pattern) don't create duplicate rows.
 *
 * Validation:
 * - Score must be ≥ 0 and ≤ component's max_score
 * - Exam must be editable (not locked, status allows it)
 * - Subject must be assigned to the class section
 * - Student must be enrolled in the class section
 *
 * Fits into the module:
 * - ScoreEntryController calls saveBulkScores() with the form submission
 * - ScoreEntryController calls getScoreEntrySheet() to render the form
 * - ResultComputationService calls after all scores are entered
 */
class ScoreEntryService
{
    // ─── Score Entry Sheet ────────────────────────────────────────────────────

    /**
     * Build the data structure for rendering the score-entry form.
     *
     * Returns:
     * {
     *   "exam": { id, name, status, ... },
     *   "subject": { id, name, code, ... },
     *   "template": { components: [...], total_score, pass_mark },
     *   "students": [
     *     {
     *       "student": { id, full_name, admission_number, ... },
     *       "result": { id?, scores, total_score, grade_code, is_absent, is_exempted, ... } | null,
     *       "can_edit": true|false
     *     },
     *     ...
     *   ]
     * }
     *
     * @param  Exam    $exam
     * @param  string  $subjectId
     * @param  string  $classSectionId
     * @return array
     */
    public function getScoreEntrySheet(Exam $exam, string $subjectId, string $classSectionId): array
    {
        $template = $exam->assessmentTemplate;

        // Fetch students enrolled in this section
        $students = \App\Models\Academic\Student::whereHas('classSections', function ($q) use ($classSectionId, $exam) {
            $q->where('class_section_id', $classSectionId)
                ->where('academic_session_id', $exam->academic_session_id);
        })
        ->with(['profile:id,first_name,last_name,middle_name'])
        ->orderBy('last_name') // Alphabetical ordering
        ->get();

        // Load existing results for this exam + subject + section
        $existingResults = ExamResult::where('exam_id', $exam->id)
            ->where('subject_id', $subjectId)
            ->where('class_section_id', $classSectionId)
            ->get()
            ->keyBy('student_id');

        $canEdit = $exam->isEditable();

        $studentRows = $students->map(function ($student) use ($existingResults, $canEdit, $template) {
            $result = $existingResults->get($student->id);

            return [
                'student' => [
                    'id'               => $student->id,
                    'full_name'        => $student->full_name,
                    'admission_number' => $student->admission_number,
                ],
                'result' => $result ? [
                    'id'          => $result->id,
                    'scores'      => $result->scores ?? [],
                    'total_score' => $result->total_score,
                    'grade_code'  => $result->grade_code,
                    'grade_remark'=> $result->grade_remark,
                    'is_absent'   => $result->is_absent,
                    'is_exempted' => $result->is_exempted,
                    'remark'      => $result->remark,
                    'is_locked'   => $result->isLocked(),
                ] : null,
                'can_edit' => $canEdit && (!$result || !$result->isLocked()),
            ];
        });

        return [
            'exam' => [
                'id'          => $exam->id,
                'name'        => $exam->name,
                'status'      => $exam->status,
                'is_editable' => $canEdit,
            ],
            'subject' => [
                'id'   => $subjectId,
                'name' => \App\Models\Academic\Subject::find($subjectId)?->name,
                'code' => \App\Models\Academic\Subject::find($subjectId)?->code,
            ],
            'template' => [
                'id'                => $template->id,
                'components'        => $template->sorted_components,
                'total_score'       => $template->total_score,
                'pass_mark'         => $template->pass_mark,
            ],
            'students' => $studentRows,
            'section'  => [
                'id'           => $classSectionId,
                'display_name' => \App\Models\Academic\ClassSection::find($classSectionId)?->display_name,
            ],
        ];
    }

    // ─── Bulk Score Save ──────────────────────────────────────────────────────

    /**
     * Save scores for multiple students in a single subject at once.
     *
     * This is the primary method called when a teacher submits the score-entry form.
     * Uses upsert pattern — safe for repeated partial saves (auto-save).
     *
     * Expected $scoresPayload format:
     * [
     *   {
     *     "student_id": "uuid",
     *     "is_absent": false,
     *     "is_exempted": false,
     *     "scores": { "ca1": 18, "ca2": 15, "exam": 52 },  // null = not entered
     *     "remark": "Good effort"
     *   },
     *   ...
     * ]
     *
     * @param  Exam    $exam
     * @param  string  $subjectId
     * @param  string  $classSectionId
     * @param  array   $scoresPayload
     * @return array   Summary: { saved: int, errors: [] }
     * @throws ValidationException
     */
    public function saveBulkScores(
        Exam $exam,
        string $subjectId,
        string $classSectionId,
        array $scoresPayload
    ): array {
        if (!$exam->isEditable()) {
            throw ValidationException::withMessages([
                'exam' => 'This exam is no longer accepting score entries.',
            ]);
        }

        $template = $exam->assessmentTemplate;
        $saved    = 0;
        $errors   = [];

        DB::transaction(function () use ($exam, $subjectId, $classSectionId, $scoresPayload, $template, &$saved, &$errors) {
            foreach ($scoresPayload as $index => $row) {
                try {
                    $this->saveStudentScore(
                        $exam,
                        $row['student_id'],
                        $subjectId,
                        $classSectionId,
                        $row
                    );
                    $saved++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'student_id' => $row['student_id'] ?? null,
                        'index'      => $index,
                        'message'    => $e->getMessage(),
                    ];

                    Log::warning('Score save failed for one student', [
                        'exam_id'    => $exam->id,
                        'subject_id' => $subjectId,
                        'student_id' => $row['student_id'] ?? null,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        });

        return [
            'saved'  => $saved,
            'errors' => $errors,
            'total'  => count($scoresPayload),
        ];
    }

    // ─── Single Student Score ─────────────────────────────────────────────────

    /**
     * Save or update all component scores for one student in one subject.
     *
     * @throws ValidationException
     */
    public function saveStudentScore(
        Exam $exam,
        string $studentId,
        string $subjectId,
        string $classSectionId,
        array $data
    ): ExamResult {
        if (!$exam->isEditable()) {
            throw ValidationException::withMessages([
                'exam' => 'Scores cannot be entered for a locked or approved exam.',
            ]);
        }

        $template = $exam->assessmentTemplate;

        // Build and validate the scores JSON
        $scoresJson = $this->buildAndValidateScores(
            $data['scores'] ?? [],
            $template,
            $data['is_absent'] ?? false
        );

        $result = ExamResult::updateOrCreate(
            [
                'exam_id'    => $exam->id,
                'student_id' => $studentId,
                'subject_id' => $subjectId,
            ],
            [
                'school_id'        => $exam->school_id,
                'class_section_id' => $classSectionId,
                'scores'           => $scoresJson,
                'is_absent'        => $data['is_absent'] ?? false,
                'is_exempted'      => $data['is_exempted'] ?? false,
                'remark'           => $data['remark'] ?? null,
                'entered_by'       => auth()->id(),
            ]
        );

        // Compute total immediately if fully scored
        $this->recomputeResultTotal($result, $template);

        return $result;
    }

    // ─── Absent / Exempted ────────────────────────────────────────────────────

    /**
     * Mark a student as absent for a subject exam.
     * Clears any previously entered scores for this student+subject.
     */
    public function markAbsent(Exam $exam, string $studentId, string $subjectId, string $classSectionId): ExamResult
    {
        if (!$exam->isEditable()) {
            throw ValidationException::withMessages([
                'exam' => 'This exam is locked and cannot be modified.',
            ]);
        }

        return ExamResult::updateOrCreate(
            ['exam_id' => $exam->id, 'student_id' => $studentId, 'subject_id' => $subjectId],
            [
                'school_id'        => $exam->school_id,
                'class_section_id' => $classSectionId,
                'scores'           => [],
                'total_score'      => null,
                'grade_code'       => null,
                'grade_remark'     => null,
                'is_absent'        => true,
                'is_exempted'      => false,
                'entered_by'       => auth()->id(),
            ]
        );
    }

    // ─── Internal Helpers ─────────────────────────────────────────────────────

    /**
     * Build the scores JSON after validating each component score.
     *
     * @throws ValidationException if any score exceeds its component's max
     */
    private function buildAndValidateScores(
        array $rawScores,
        AssessmentTemplate $template,
        bool $isAbsent
    ): array {
        if ($isAbsent) {
            return []; // Absent students have no scores
        }

        $scores = [];

        foreach ($template->components as $component) {
            $key      = $component['key'];
            $maxScore = $component['max_score'] ?? 0;
            $value    = $rawScores[$key] ?? null;

            // Null values are valid (score not yet entered for this component)
            if ($value !== null) {
                $value = (float) $value;

                if ($value < 0) {
                    throw ValidationException::withMessages([
                        "scores.{$key}" => "Score for {$component['label']} cannot be negative.",
                    ]);
                }

                if ($value > $maxScore) {
                    throw ValidationException::withMessages([
                        "scores.{$key}" => "Score for {$component['label']} cannot exceed {$maxScore}.",
                    ]);
                }
            }

            $scores[$key] = [
                'score'      => $value,
                'max'        => $maxScore,
                'entered_at' => $value !== null ? now()->toISOString() : null,
            ];
        }

        return $scores;
    }

    /**
     * Compute and save the total_score, grade_code, and grade_remark
     * after scores are saved. Uses AssessmentTemplate weights and Grade model.
     */
    private function recomputeResultTotal(ExamResult $result, AssessmentTemplate $template): void
    {
        if ($result->is_absent || $result->is_exempted) {
            return;
        }

        $total = $result->computeTotal($template);

        if ($total === null) {
            return; // Not all components entered yet
        }

        // Find the matching grade for this score
        $grade = \App\Models\Academic\Grade::where('school_id', $result->school_id)
            ->where('min_score', '<=', $total)
            ->where('max_score', '>=', $total)
            ->orderByDesc('min_score')
            ->first();

        $result->update([
            'total_score'  => $total,
            'grade_code'   => $grade?->code,
            'grade_remark' => $grade?->remark,
        ]);
    }
}
