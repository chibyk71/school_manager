<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Academic\ComputedResultResource;
use App\Models\Academic\ComputedResult;
use App\Models\Academic\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * ExamResultsController
 *
 * Serves aggregated exam results for the class results table and per-student views.
 * Reads from `computed_results` — never from raw `exam_results` directly.
 *
 * Routes:
 * ─────────────────────────────────────────────────────────────────────────────
 * GET /exams/{exam}/results                       → index  (class results table)
 * GET /exams/{exam}/results/{student}             → show   (single student detail)
 * PATCH /exams/{exam}/results/{result}/remark     → updateRemark (teacher/principal remark)
 *
 * Features / Problems Solved:
 * - `index()` returns all computed_results for a given exam, optionally filtered by section
 * - Supports sorting by position, average_score, or student name
 * - `updateRemark()` allows the class teacher and principal to add/edit remarks
 *   even after results are approved (remarks don't affect scores/grades)
 * - `show()` returns the full subject breakdown for one student (used for report card data)
 */
class ExamResultsController extends Controller
{
    /**
     * Display the full class results table for an exam.
     *
     * Supports filtering by section_id (when a level-wide exam has multiple sections).
     * Sorted by position_in_class ascending by default (1st, 2nd, 3rd...).
     */
    public function index(Request $request, Exam $exam)
    {
        Gate::authorize('view', $exam);

        try {
            $sectionId = $request->input('section_id');

            $results = ComputedResult::where('exam_id', $exam->id)
                ->when($sectionId, fn($q) => $q->where('class_section_id', $sectionId))
                ->with([
                    'student.profile:id,first_name,last_name,middle_name',
                    'classSection:id,display_name',
                ])
                ->orderBy('position_in_class')
                ->get();

            // Compute class stats for the header summary
            $classStats = [
                'total_students'  => $results->count(),
                'average_score'   => round($results->avg('average_score') ?? 0, 2),
                'highest_average' => $results->max('average_score'),
                'lowest_average'  => $results->min('average_score'),
                'total_passed'    => $results->where('subjects_failed', 0)->count(),
                'total_failed'    => $results->where('subjects_failed', '>', 0)->count(),
            ];

            // Group by section for multi-section exams
            $sections = $exam->getApplicableSections()
                ->map(fn($s) => ['id' => $s->id, 'display_name' => $s->display_name]);

            $exam->load(['academicSession:id,name', 'term:id,name', 'classLevel:id,name', 'assessmentTemplate']);

            if ($request->wantsJson()) {
                return response()->json([
                    'results'     => ComputedResultResource::collection($results),
                    'class_stats' => $classStats,
                ]);
            }

            return Inertia::render('Academic/Exams/Results', [
                'exam'       => [
                    'id'           => $exam->id,
                    'name'         => $exam->name,
                    'status'       => $exam->status,
                    'is_locked'    => $exam->isLocked(),
                    'session_name' => $exam->academicSession->name,
                    'term_name'    => $exam->term?->name,
                    'level_name'   => $exam->classLevel?->name,
                    'template'     => [
                        'components' => $exam->assessmentTemplate->sorted_components,
                        'total_score'=> $exam->assessmentTemplate->total_score,
                        'pass_mark'  => $exam->assessmentTemplate->pass_mark,
                    ],
                ],
                'results'      => ComputedResultResource::collection($results),
                'class_stats'  => $classStats,
                'sections'     => $sections,
                'active_section_id' => $sectionId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to load exam results', [
                'exam_id' => $exam->id,
                'error'   => $e->getMessage(),
            ]);

            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to load results.'], 500)
                : back()->with('error', 'Failed to load results.');
        }
    }

    /**
     * Get a single student's full computed result (for report card).
     */
    public function show(Request $request, Exam $exam, string $studentId)
    {
        Gate::authorize('view', $exam);

        $result = ComputedResult::where('exam_id', $exam->id)
            ->where('student_id', $studentId)
            ->with([
                'student.profile:id,first_name,last_name,middle_name',
                'classSection:id,display_name',
            ])
            ->firstOrFail();

        return response()->json(new ComputedResultResource($result));
    }

    /**
     * Update teacher/principal remark for a specific student's result.
     * Allowed even after results_approved — remarks don't affect scores.
     */
    public function updateRemark(Request $request, Exam $exam, ComputedResult $result)
    {
        Gate::authorize('update', $exam);

        $validated = $request->validate([
            'class_teacher_remark' => 'nullable|string|max:500',
            'principal_remark'     => 'nullable|string|max:500',
        ]);

        // Only update fields that were actually sent
        $toUpdate = array_filter($validated, fn($v) => $v !== null, ARRAY_FILTER_USE_KEY);

        // Check which role is making the update and restrict accordingly
        $user = $request->user();
        $updateData = [];

        if (isset($toUpdate['class_teacher_remark'])) {
            // Allow class teachers and admins
            $updateData['class_teacher_remark'] = $toUpdate['class_teacher_remark'];
        }

        if (isset($toUpdate['principal_remark'])) {
            // Only admins/principals can set principal_remark
            if ($user->hasRole(['admin', 'principal', 'super-admin'])) {
                $updateData['principal_remark'] = $toUpdate['principal_remark'];
            }
        }

        $result->update($updateData);

        return response()->json([
            'message' => 'Remark updated.',
            'result'  => new ComputedResultResource($result),
        ]);
    }
}
