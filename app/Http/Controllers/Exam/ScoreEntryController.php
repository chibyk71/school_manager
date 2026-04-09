<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\Exam;
use App\Models\Academic\ClassSection;
use App\Models\Academic\Subject;
use App\Services\Academic\ScoreEntryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * ScoreEntryController
 *
 * Handles the teacher-facing score entry interface.
 *
 * Routes:
 * ─────────────────────────────────────────────────────────────────────────────
 * GET  /exams/{exam}/scores/{section}/{subject}  → show     (score-entry sheet)
 * POST /exams/{exam}/scores/{section}/{subject}  → store    (bulk score save)
 * POST /exams/{exam}/scores/{section}/{subject}/absent → markAbsent
 *
 * Features / Problems Solved:
 * - `show()` builds the complete score-entry sheet (students + existing scores + template)
 *   with a single service call — zero extra DB queries in the controller
 * - `store()` handles partial saves (auto-save) and full saves uniformly
 * - Response includes per-row errors (some students fail validation, others save)
 * - Both Inertia and JSON responses supported (for auto-save AJAX)
 * - Score entry is blocked if the exam is locked or not in an editable state
 */
class ScoreEntryController extends Controller
{
    public function __construct(protected ScoreEntryService $service)
    {
    }

    /**
     * Render the score-entry sheet for a specific subject + section.
     *
     * The teacher selects an exam → section → subject and is shown
     * a table with all students and component-level score inputs.
     */
    public function show(Request $request, Exam $exam, string $sectionId, string $subjectId)
    {
        Gate::authorize('view', $exam);

        try {
            $sheet = $this->service->getScoreEntrySheet($exam, $subjectId, $sectionId);

            if ($request->wantsJson()) {
                return response()->json($sheet);
            }

            return Inertia::render('Academic/Exams/ScoreEntry', [
                'exam'      => $sheet['exam'],
                'subject'   => $sheet['subject'],
                'template'  => $sheet['template'],
                'students'  => $sheet['students'],
                'section'   => $sheet['section'],
                // Sidebar: list of all subjects with completion status for navigation
                'allSubjects' => $this->getSubjectsProgress($exam, $sectionId),
            ]);
        } catch (\Throwable $e) {
            Log::error('Score entry sheet load failed', [
                'exam_id'    => $exam->id,
                'section_id' => $sectionId,
                'subject_id' => $subjectId,
                'error'      => $e->getMessage(),
            ]);

            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to load score entry sheet.'], 500)
                : back()->with('error', 'Failed to load score entry sheet.');
        }
    }

    /**
     * Save bulk scores for a subject+section.
     *
     * Supports:
     * - Auto-save (AJAX, single student, partial score)
     * - Full save (form submit, all students)
     *
     * Returns per-row results so the frontend can highlight failures
     * without losing successfully saved rows.
     */
    public function store(Request $request, Exam $exam, string $sectionId, string $subjectId)
    {
        Gate::authorize('update', $exam);

        $request->validate([
            'scores'                 => 'required|array',
            'scores.*.student_id'    => 'required|uuid|exists:students,id',
            'scores.*.is_absent'     => 'boolean',
            'scores.*.is_exempted'   => 'boolean',
            'scores.*.remark'        => 'nullable|string|max:500',
            'scores.*.scores'        => 'nullable|array',
        ]);

        try {
            $result = $this->service->saveBulkScores(
                $exam,
                $subjectId,
                $sectionId,
                $request->input('scores')
            );

            $message = $result['errors']
                ? "Saved {$result['saved']} scores. {$result['errors'][0]['message']}"
                : "Saved {$result['saved']} scores.";

            return response()->json([
                'message' => $message,
                'saved'   => $result['saved'],
                'errors'  => $result['errors'],
            ], $result['errors'] ? 207 : 200); // 207 Multi-Status when some fail
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Bulk score save failed', [
                'exam_id'    => $exam->id,
                'section_id' => $sectionId,
                'subject_id' => $subjectId,
                'error'      => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to save scores. Please try again.'], 500);
        }
    }

    /**
     * Mark a student as absent for a subject exam.
     */
    public function markAbsent(Request $request, Exam $exam, string $sectionId, string $subjectId)
    {
        Gate::authorize('update', $exam);

        $request->validate(['student_id' => 'required|uuid|exists:students,id']);

        try {
            $result = $this->service->markAbsent(
                $exam,
                $request->input('student_id'),
                $subjectId,
                $sectionId
            );

            return response()->json([
                'message' => 'Student marked absent.',
                'result'  => ['id' => $result->id, 'is_absent' => true],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to mark absent.'], 500);
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Build the sidebar list of subjects with how many students have been fully scored.
     * Used by the score-entry page sidebar for navigation.
     */
    private function getSubjectsProgress(Exam $exam, string $sectionId): array
    {
        // Find subjects assigned to this section via teacher-subject assignments
        $subjects = Subject::whereHas('teacherAssignments', function ($q) use ($sectionId) {
            $q->where('class_section_id', $sectionId);
        })->orderBy('name')->get(['id', 'name', 'code']);

        return $subjects->map(function ($subject) use ($exam, $sectionId) {
            $totalStudents = \App\Models\Academic\Student::whereHas('classSections', function ($q) use ($sectionId, $exam) {
                $q->where('class_section_id', $sectionId)
                    ->where('academic_session_id', $exam->academic_session_id);
            })->count();

            $scored = \App\Models\Academic\ExamResult::where('exam_id', $exam->id)
                ->where('subject_id', $subject->id)
                ->where('class_section_id', $sectionId)
                ->whereNotNull('total_score')
                ->count();

            $isComplete = $totalStudents > 0 && $scored >= $totalStudents;

            return [
                'id'              => $subject->id,
                'name'            => $subject->name,
                'code'            => $subject->code,
                'total_students'  => $totalStudents,
                'scores_entered'  => $scored,
                'is_complete'     => $isComplete,
            ];
        })->toArray();
    }
}
