<?php

namespace App\Http\Controllers\Settings\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Models\Academic\Subject;
use App\Models\SchoolSection;
use App\Services\Academic\SubjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * SubjectController v2.0 – Production-Ready Subjects Management
 *
 * Responsibilities (HTTP only — all domain logic lives in SubjectService):
 * ─────────────────────────────────────────────────────────────────────────
 * • index   – Serve the Inertia page or a JSON DataTable response
 * • store   – Create a subject + sync sections via service
 * • show    – Return a single subject as JSON (for edit-modal pre-fill)
 * • update  – Update a subject + re-sync sections via service
 * • destroy – Bulk soft-delete or force-delete via service
 * • restore – Restore one soft-deleted subject via service
 *
 * Authorization:
 * ─────────────────────────────────────────────────────────────────────────
 * Every action is gated through SubjectPolicy, registered in AuthServiceProvider.
 * Gate::authorize() is used directly so Laravel automatically returns a 403
 * JSON response for wantsJson() requests and a Symfony HttpException otherwise.
 *
 * Response strategy:
 * ─────────────────────────────────────────────────────────────────────────
 * • wantsJson() → pure JSON (DataTable AJAX, modal submit, mobile API)
 * • otherwise   → Inertia redirect with flash (web form fallback)
 *
 * v2 Changes vs v1:
 * ─────────────────────────────────────────────────────────────────────────
 * • Moved to correct namespace: Settings\Academic (was root Controllers)
 * • Replaced permitted() with Gate::authorize() + SubjectPolicy
 * • Delegated all business logic to SubjectService (no direct Eloquent in store/update/destroy)
 * • Wrapped responses in SubjectResource for consistent JSON shape
 * • Added typeOptions / categoryOptions props (DynamicEnum-backed)
 * • destroy() now goes through service (respects usage-guard, events, logging)
 * • restore() now goes through service (conflict checks, fresh reload)
 * • Replaced broad catch(\Exception) with \Throwable for completeness
 * • Removed direct school_id injection (handled by BelongsToSchool + FormRequest)
 */
class SubjectController extends Controller
{
    public function __construct(protected SubjectService $service) {}

    // ─────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────

    /**
     * List subjects with server-side DataTable support.
     *
     * Accepts an optional {schoolSection} route parameter to pre-filter
     * subjects belonging to a specific section (nested resource pattern).
     * Passes typeOptions / categoryOptions from DynamicEnum so the frontend
     * modal dropdowns always show the school's custom values.
     */
    public function index(Request $request, ?SchoolSection $schoolSection = null)
    {
        Gate::authorize('viewAny', Subject::class);

        try {
            $extraFields = [
                [
                    'field'        => 'school_section_names',
                    'relation'     => 'schoolSections',
                    'relatedField' => 'name',
                    'filterable'   => true,
                    'sortable'     => false,
                    'filterType'   => 'text',
                ],
            ];

            $query = Subject::with(['schoolSections:id,name'])
                ->when($schoolSection, fn($q) => $q->inSection($schoolSection->id))
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            $result = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return SubjectResource::collection($result['data'])
                    ->additional(['meta' => collect($result)->except('data')]);
            }

            return Inertia::render('Settings/Academic/Subjects', [
                'schoolSection'   => $schoolSection?->only('id', 'name'),
                'subjects'        => $result,
                'typeOptions'     => Subject::typeOptions(),
                'categoryOptions' => Subject::categoryOptions(),
                'schoolSections'  => SchoolSection::select('id', 'name')
                    ->orderBy('name')
                    ->get(),
                'crumbs' => [
                    ['label' => 'Settings'],
                    ['label' => 'Academic'],
                    ['label' => 'Subjects'],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('SubjectController@index failed', [
                'error'             => $e->getMessage(),
                'school_section_id' => $schoolSection?->id,
            ]);

            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to load subjects.'], 500)
                : back()->with('error', 'Failed to load subjects.');
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Store
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Create a new subject.
     *
     * FormRequest handles validation + school_id injection via prepareForValidation().
     * SubjectService::create() handles the DB write, section sync, and events.
     */
    public function store(StoreSubjectRequest $request)
    {
        Gate::authorize('create', Subject::class);

        $result = $this->service->create($request->validated());

        if (! $result['success']) {
            return $request->wantsJson()
                ? response()->json(['error' => $result['message']], 422)
                : back()->withInput()->with('error', $result['message']);
        }

        $subject = $result['data']->load('schoolSections:id,name');

        return $request->wantsJson()
            ? response()->json([
                'message' => $result['message'],
                'subject' => new SubjectResource($subject),
            ], 201)
            : back()->with('success', $result['message']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Return a single subject for edit-modal pre-fill (JSON only).
     */
    public function show(Subject $subject)
    {
        Gate::authorize('view', $subject);

        try {
            $subject->load('schoolSections:id,name');

            return response()->json(new SubjectResource($subject));
        } catch (\Throwable $e) {
            Log::error('SubjectController@show failed', [
                'subject_id' => $subject->id,
                'error'      => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to load subject.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Update
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Update an existing subject.
     *
     * Section sync is handled by SubjectService::update() — passing an empty
     * school_section_ids array explicitly detaches all sections.
     */
    public function update(UpdateSubjectRequest $request, Subject $subject)
    {
        Gate::authorize('update', $subject);

        $result = $this->service->update($subject, $request->validated());

        if (! $result['success']) {
            return $request->wantsJson()
                ? response()->json(['error' => $result['message']], 422)
                : back()->withInput()->with('error', $result['message']);
        }

        $updated = $result['data']->load('schoolSections:id,name');

        return $request->wantsJson()
            ? response()->json([
                'message' => $result['message'],
                'subject' => new SubjectResource($updated),
            ])
            : back()->with('success', $result['message']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Destroy (bulk soft-delete or force-delete)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Soft-delete or permanently delete one or more subjects.
     *
     * Expects JSON body: { ids: string[], force?: boolean }
     * Force-delete requires the 'forceDelete' policy ability.
     */
    public function destroy(Request $request)
    {
        try {
            $request->validate([
                'ids'   => 'required|array|min:1',
                'ids.*' => 'exists:subjects,id',
                'force' => 'sometimes|boolean',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Invalid input.', 'errors' => $e->errors()], 422);
        }

        $force = $request->boolean('force', false);

        Gate::authorize($force ? 'forceDelete' : 'delete', Subject::class);

        $result = $force
            ? $this->service->bulkForceDelete($request->input('ids'))
            : $this->service->bulkDelete($request->input('ids'));

        return $result['success']
            ? response()->json(['message' => $result['message']])
            : response()->json(['error' => $result['message']], 422);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Restore
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Restore a single soft-deleted subject (JSON only).
     */
    public function restore(string $id)
    {
        try {
            $subject = Subject::withTrashed()->findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['error' => 'Subject not found.'], 404);
        }

        Gate::authorize('restore', $subject);

        $result = $this->service->restore($subject);

        return $result['success']
            ? response()->json([
                'message' => $result['message'],
                'subject' => new SubjectResource($result['data']),
            ])
            : response()->json(['error' => $result['message']], 422);
    }
}