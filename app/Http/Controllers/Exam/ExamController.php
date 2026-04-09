<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreExamRequest;
use App\Http\Requests\Academic\UpdateExamRequest;
use App\Http\Resources\Academic\ExamResource;
use App\Jobs\Academic\ComputeExamResultsJob;
use App\Models\Academic\AssessmentTemplate;
use App\Models\Academic\Exam;
use App\Services\Academic\ExamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * ExamController
 *
 * Handles HTTP layer for Exam CRUD and lifecycle actions.
 *
 * Routes managed:
 * ─────────────────────────────────────────────────────────────────────────────
 * GET    /exams                  → index      (list with DataTable)
 * POST   /exams                  → store      (create exam)
 * GET    /exams/{exam}           → show       (detail / score entry hub)
 * PATCH  /exams/{exam}           → update     (edit exam details)
 * DELETE /exams                  → destroy    (bulk soft-delete)
 * PATCH  /exams/{exam}/status    → updateStatus (transition status machine)
 * POST   /exams/{exam}/compute   → computeResults (dispatch computation job)
 * POST   /exams/{exam}/restore   → restore    (restore soft-deleted)
 *
 * Authorization:
 * - All actions gated by ExamPolicy (to be created)
 * - Score entry and result approval use separate permissions
 *
 * Features / Problems Solved:
 * - Delegates all business logic to ExamService (controller = thin HTTP layer)
 * - Inertia + JSON dual response for DataTable
 * - Status transitions via dedicated endpoint (not bundled with update)
 * - Result computation dispatched as a queued job to prevent timeout
 */
class ExamController extends Controller
{
    public function __construct(protected ExamService $service)
    {
    }

    /**
     * List all exams with DataTable support.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Exam::class);

        try {
            $extra = [
                ['field' => 'session_name',  'relation' => 'academicSession', 'relatedField' => 'name'],
                ['field' => 'term_name',      'relation' => 'term',            'relatedField' => 'name'],
                ['field' => 'level_name',     'relation' => 'classLevel',      'relatedField' => 'name'],
                ['field' => 'template_name',  'relation' => 'assessmentTemplate', 'relatedField' => 'name'],
            ];

            $query = Exam::with(['academicSession:id,name', 'term:id,name', 'classLevel:id,name', 'assessmentTemplate:id,name'])
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed())
                ->when($request->input('term_id'), fn($q, $id) => $q->where('term_id', $id))
                ->when($request->input('status'), fn($q, $s) => $q->where('status', $s));

            $result = $query->tableQuery($request, $extra);

            if ($request->wantsJson()) {
                return ExamResource::collection($result['data'])
                    ->additional(['meta' => [
                        'total'    => $result['totalRecords'],
                        'columns'  => $result['columns'],
                    ]]);
            }

            // Load support data for filters
            $currentSession = currentSession();
            $currentTerm    = currentTerm();

            return Inertia::render('Academic/Exams/Index', [
                'exams'          => ExamResource::collection($result['data']),
                'totalRecords'   => $result['totalRecords'],
                'columns'        => $result['columns'],
                'currentSession' => $currentSession?->only('id', 'name'),
                'currentTerm'    => $currentTerm?->only('id', 'name'),
                'statuses'       => Exam::VALID_STATUSES,
            ]);
        } catch (\Throwable $e) {
            Log::error('Exam index failed', ['error' => $e->getMessage()]);

            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to load exams.'], 500)
                : Inertia::render('Academic/Exams/Index', ['exams' => [], 'error' => 'Failed to load exams.']);
        }
    }

    /**
     * Show a single exam — the hub for score entry, timetable, and results.
     */
    public function show(Request $request, Exam $exam)
    {
        Gate::authorize('view', $exam);

        $exam->load([
            'assessmentTemplate',
            'academicSession:id,name',
            'term:id,name',
            'classLevel:id,name',
            'classSection:id,display_name',
            'timetable.subject:id,name,code',
        ]);

        // Score entry progress per section
        $sections    = $exam->getApplicableSections()->load('classLevel:id,name');
        $sectionData = $sections->map(function ($section) use ($exam) {
            $totalExpected = \App\Models\Academic\Student::whereHas('classSections', function ($q) use ($section, $exam) {
                $q->where('class_section_id', $section->id)
                    ->where('academic_session_id', $exam->academic_session_id);
            })->count();

            $scored = $exam->examResults()
                ->where('class_section_id', $section->id)
                ->whereNotNull('total_score')
                ->count();

            return [
                'section'       => $section->only('id', 'display_name'),
                'total_students'=> $totalExpected,
                'scores_entered'=> $scored,
                'progress'      => $totalExpected > 0 ? round(($scored / $totalExpected) * 100, 1) : 0,
            ];
        });

        $hasComputedResults = $exam->computedResults()->exists();

        return Inertia::render('Academic/Exams/Show', [
            'exam'               => new ExamResource($exam),
            'sections'           => $sectionData,
            'hasComputedResults' => $hasComputedResults,
            'canCompute'         => in_array($exam->status, [Exam::STATUS_COMPLETED], true),
            'canApprove'         => $exam->isCompleted() && $hasComputedResults,
        ]);
    }

    /**
     * Create an exam.
     */
    public function store(StoreExamRequest $request)
    {
        Gate::authorize('create', Exam::class);

        try {
            $exam = $this->service->create($request->validated());

            return $request->wantsJson()
                ? response()->json(['exam' => new ExamResource($exam), 'message' => 'Exam created.'], 201)
                : redirect()->route('exams.show', $exam)->with('success', "Exam '{$exam->name}' created.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            Log::error('Exam create failed', ['error' => $e->getMessage()]);
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create exam.'], 500)
                : back()->with('error', 'Failed to create exam.')->withInput();
        }
    }

    /**
     * Update exam details.
     */
    public function update(UpdateExamRequest $request, Exam $exam)
    {
        Gate::authorize('update', $exam);

        try {
            $exam = $this->service->update($exam, $request->validated());

            return $request->wantsJson()
                ? response()->json(['exam' => new ExamResource($exam), 'message' => 'Exam updated.'])
                : back()->with('success', 'Exam updated.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : back()->withErrors($e->errors())->withInput();
        }
    }

    /**
     * Transition the exam status (publish, mark ongoing, complete, approve).
     */
    public function updateStatus(Request $request, Exam $exam)
    {
        Gate::authorize('update', $exam);

        $request->validate([
            'status'              => ['required', 'string', \Illuminate\Validation\Rule::in(Exam::VALID_STATUSES)],
            'publish_to_students' => 'sometimes|boolean',
        ]);

        try {
            $exam = $this->service->transitionStatus(
                $exam,
                $request->input('status'),
                $request->only('publish_to_students')
            );

            $labels = [
                Exam::STATUS_PUBLISHED        => 'published',
                Exam::STATUS_ONGOING          => 'marked as ongoing',
                Exam::STATUS_COMPLETED        => 'marked as completed',
                Exam::STATUS_RESULTS_APPROVED => 'results approved and locked',
                Exam::STATUS_DRAFT            => 'reverted to draft',
            ];

            $message = "Exam {$labels[$exam->status]}.";

            return $request->wantsJson()
                ? response()->json(['exam' => new ExamResource($exam), 'message' => $message])
                : back()->with('success', $message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : back()->withErrors($e->errors());
        }
    }

    /**
     * Dispatch the result computation job for this exam.
     * Returns immediately — job runs asynchronously.
     */
    public function computeResults(Request $request, Exam $exam)
    {
        Gate::authorize('update', $exam);

        if (!in_array($exam->status, [Exam::STATUS_COMPLETED, Exam::STATUS_ONGOING], true)) {
            return response()->json(['error' => 'Exam must be completed or ongoing before computing results.'], 422);
        }

        ComputeExamResultsJob::dispatch($exam);

        return response()->json(['message' => 'Result computation started. This may take a moment.']);
    }

    /**
     * Bulk soft-delete exams.
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', Exam::class);

        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'exists:exams,id',
        ]);

        $deleted = 0;
        $errors  = [];

        foreach ($request->input('ids') as $id) {
            $exam = Exam::find($id);
            if (!$exam) continue;

            try {
                $this->service->delete($exam);
                $deleted++;
            } catch (\Illuminate\Validation\ValidationException $e) {
                $errors[] = ['id' => $id, 'message' => collect($e->errors())->flatten()->first()];
            }
        }

        return response()->json([
            'deleted' => $deleted,
            'errors'  => $errors,
            'message' => "{$deleted} exam(s) deleted.",
        ]);
    }

    /**
     * Restore a soft-deleted exam.
     */
    public function restore(string $id)
    {
        $exam = Exam::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $exam);

        $exam->restore();

        return back()->with('success', "Exam '{$exam->name}' restored.");
    }
}
