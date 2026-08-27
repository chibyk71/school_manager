<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\ComputedResult;
use App\Models\Academic\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * ReportCardController
 *
 * Serves the report card view for a single student in a specific exam.
 *
 * Routes:
 * ─────────────────────────────────────────────────────────────────────────────
 * GET /exams/{exam}/report-cards/{student}           → show   (single report card)
 * GET /exams/{exam}/report-cards                     → index  (print all for a section)
 * GET /exams/{exam}/report-cards/{student}/print     → printView (print-optimized HTML)
 *
 * The report card data comes entirely from `computed_results.subject_breakdown` —
 * a frozen snapshot taken at computation time. This means:
 * - Report cards NEVER change after approval, even if grade scales are edited later
 * - No joins to exam_results needed for rendering — everything is in one row
 * - Bulk printing can be done by loading all computed_results for a section at once
 *
 * What is included:
 * - School branding (name, logo, address from school settings)
 * - Student details (name, admission number, class, session/term)
 * - Per-subject score table with component breakdown, total, grade, remark
 * - Class position and average
 * - Teacher and principal remarks
 * - Promotion status (shown after promotion service runs)
 *
 * Print strategy:
 * - `printView()` returns a minimal HTML page with `@media print` CSS
 * - The frontend calls `window.print()` inside the iframe
 * - For bulk, all cards are stacked with `page-break-after: always` CSS
 */
class ReportCardController extends Controller
{
    /**
     * Show a single student's report card (Inertia page).
     */
    public function show(Request $request, Exam $exam, string $studentId)
    {
        Gate::authorize('view', $exam);

        // Exam must be at least completed before showing report cards
        if ($exam->isDraft() || $exam->isPublished()) {
            return back()->with('error', 'Report cards are not available until the exam is completed.');
        }

        $result = ComputedResult::where('exam_id', $exam->id)
            ->where('student_id', $studentId)
            ->with([
                'student.profile:id,first_name,last_name,middle_name',
                'classSection:id,display_name',
                'classSection.classLevel:id,name,display_name',
            ])
            ->firstOrFail();

        $school = GetSchoolModel();
        $schoolSettings = getMergedSettings('company', $school);

        $exam->load(['academicSession:id,name', 'term:id,name', 'classLevel:id,name,display_name']);

        return Inertia::render('Academic/Exams/ReportCard', [
            'result' => [
                'id'                   => $result->id,
                'total_score_obtained' => $result->total_score_obtained,
                'total_score_possible' => $result->total_score_possible,
                'average_score'        => $result->average_score,
                'position_in_class'    => $result->position_in_class,
                'class_size'           => $result->class_size,
                'subjects_passed'      => $result->subjects_passed,
                'subjects_failed'      => $result->subjects_failed,
                'subject_breakdown'    => $result->subject_breakdown ?? [],
                'class_teacher_remark' => $result->class_teacher_remark,
                'principal_remark'     => $result->principal_remark,
                'promotion_status'     => $result->promotion_status,
                'is_final'             => $result->is_final,
            ],
            'student' => [
                'id'               => $result->student->id,
                'full_name'        => $result->student->full_name,
                'admission_number' => $result->student->admission_number,
                'photo_url'        => $result->student->photo_url,
                'class_name'       => $result->classSection?->display_name,
                'level_name'       => $result->classSection?->classLevel?->display_name
                    ?? $exam->classLevel?->display_name,
            ],
            'exam' => [
                'id'           => $exam->id,
                'name'         => $exam->name,
                'session_name' => $exam->academicSession->name,
                'term_name'    => $exam->term?->name,
                'status'       => $exam->status,
            ],
            'school' => [
                'name'    => $school?->name,
                'logo'    => $school?->logo_url ?? null,
                'address' => $schoolSettings['address'] ?? null,
                'phone'   => $schoolSettings['public_phone'] ?? null,
                'email'   => $schoolSettings['public_email'] ?? null,
                'motto'   => $school?->motto ?? null,
            ],
            'template' => [
                'components'  => $exam->assessmentTemplate->sorted_components,
                'total_score' => $exam->assessmentTemplate->total_score,
                'pass_mark'   => $exam->assessmentTemplate->pass_mark,
            ],
            'can_edit_remarks'    => !$exam->isDraft(),
            'can_approve_results' => $exam->isCompleted() && $result->subjects_scored > 0,
        ]);
    }

    /**
     * Return all report cards for a section — for bulk printing.
     * Renders a print-optimized page with all students stacked.
     */
    public function bulkPrint(Request $request, Exam $exam)
    {
        Gate::authorize('view', $exam);

        $sectionId = $request->input('section_id');

        $results = ComputedResult::where('exam_id', $exam->id)
            ->when($sectionId, fn($q) => $q->where('class_section_id', $sectionId))
            ->with([
                'student.profile:id,first_name,last_name,middle_name',
                'classSection:id,display_name',
                'classSection.classLevel:id,name,display_name',
            ])
            ->orderBy('position_in_class')
            ->get();

        $school = GetSchoolModel();
        $schoolSettings = getMergedSettings('company', $school);
        $exam->load(['academicSession:id,name', 'term:id,name', 'classLevel:id,name,display_name', 'assessmentTemplate']);

        return Inertia::render('Academic/Exams/ReportCardBulk', [
            'results'  => $results->map(fn($r) => [
                'id'                   => $r->id,
                'student'              => [
                    'id'               => $r->student->id,
                    'full_name'        => $r->student->full_name,
                    'admission_number' => $r->student->admission_number,
                    'photo_url'        => $r->student->photo_url,
                    'class_name'       => $r->classSection?->display_name,
                ],
                'total_score_obtained' => $r->total_score_obtained,
                'total_score_possible' => $r->total_score_possible,
                'average_score'        => $r->average_score,
                'position_in_class'    => $r->position_in_class,
                'class_size'           => $r->class_size,
                'subjects_passed'      => $r->subjects_passed,
                'subjects_failed'      => $r->subjects_failed,
                'subject_breakdown'    => $r->subject_breakdown ?? [],
                'class_teacher_remark' => $r->class_teacher_remark,
                'principal_remark'     => $r->principal_remark,
                'promotion_status'     => $r->promotion_status,
            ]),
            'exam'    => [
                'name'         => $exam->name,
                'session_name' => $exam->academicSession->name,
                'term_name'    => $exam->term?->name,
            ],
            'school'   => [
                'name'  => $school?->name,
                'logo'  => $school?->logo_url ?? null,
                'phone' => $schoolSettings['public_phone'] ?? null,
                'email' => $schoolSettings['public_email'] ?? null,
                'motto' => $school?->motto ?? null,
            ],
            'template' => [
                'components'  => $exam->assessmentTemplate->sorted_components,
                'total_score' => $exam->assessmentTemplate->total_score,
                'pass_mark'   => $exam->assessmentTemplate->pass_mark,
            ],
        ]);
    }
}
