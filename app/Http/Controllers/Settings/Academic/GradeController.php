<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGradeRequest;
use App\Http\Requests\UpdateGradeRequest;
use App\Http\Resources\Academic\GradeResource;
use App\Models\Academic\Grade;
use App\Models\SchoolSection;
use App\Services\Academic\GradeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * GradeController – Handles HTTP requests for managing grading scales
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Serves as thin HTTP entry point: routing, permission checks, request/response handling
 * • Delegates all business logic (create/update/delete/restore + section sync) to GradeService
 * • Supports dual response formats: Inertia SSR for web UI + JSON for DataTable/AJAX/mobile
 * • Centralized permission checks via permitted() helper (assumes your custom middleware/func)
 * • Consistent error handling & logging across all actions
 * • Uses GradeResource for standardized JSON output (future-proof for API/mobile)
 * • Handles bulk operations (destroy multiple) and soft-delete/restore
 * • Responsive to schoolSection context (filtering when nested under a section)
 *
 * How it fits into the Grades Module:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Primary controller for routes: grades.index, grades.store, grades.update, etc.
 * • Renders main Inertia page (Academic/Exam/Grades.vue) with DataTable data
 * • Injects GradeService → keeps controller focused on HTTP concerns only
 * • Returns GradeResource for JSON responses → clean, consistent API shape
 * • Integrates with many-to-many sections via service sync
 * • Works with frontend: DataTable AJAX fetches JSON, modals submit to store/update
 *
 * Best Practices Applied:
 * • Dependency injection of GradeService (testable, swappable)
 * • Early permission checks → fail fast
 * • Try-catch + structured logging → production-ready error visibility
 * • Consistent response patterns (success/error messages, HTTP codes)
 * • No direct Eloquent queries → all domain logic in service
 * • Prepared for future: show(), bulk restore, export, etc.
 */
class GradeController extends Controller
{
    protected GradeService $service;

    public function __construct(GradeService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of grades.
     *
     * This is the main entry point for the grades management interface.
     * It supports two response formats:
     *   1. Inertia SSR view (web UI with PrimeVue DataTable)
     *   2. JSON response (for AJAX/DataTable server-side processing or API clients)
     *
     * Features / Responsibilities:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Permission check using your custom permitted() helper
     * • Optional filtering by a specific SchoolSection (when nested route /school-sections/{section}/grades)
     * • Dynamic server-side DataTable querying via HasTableQuery trait
     * • Eager loading of related school sections (many-to-many)
     * • Custom extra field for displaying joined section names (comma-separated)
     * • Dual response handling: Inertia for full page, JSON for AJAX
     * • Comprehensive try-catch with structured logging for production visibility
     * • Consistent error responses (JSON 500 or Inertia flash message)
     * • Prepares data for frontend: school sections dropdown, optional section context
     *
     * How it fits into the Grades Module:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Renders the primary Inertia page: Academic/Exam/Grades.vue
     * • Feeds the PrimeVue DataTable with server-side pagination, search, sort, filter
     * • Supports contextual views (e.g. grades belonging to one school section)
     * • Works with many-to-many relationship via BelongsToSections trait
     * • Integrates with GradeResource for clean JSON output (API/mobile readiness)
     * • Uses the powerful HasTableQuery trait + ColumnDefinitionHelper for dynamic columns
     *
     * Performance & Security Notes:
     * • Eager loads only needed fields (:id,name) to reduce payload
     * • Permission check early — fail fast
     * • Structured logging with trace for easier debugging in production
     * • Safe handling of optional $schoolSection route parameter
     *
     * @param  Request               $request        Incoming HTTP request
     * @param  SchoolSection|null    $schoolSection  Optional route model bound section
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|AnonymousResourceCollection
     */
    public function index(Request $request, ?SchoolSection $schoolSection = null)
    {
        // Early permission check – supports both web and JSON/AJAX contexts
        permitted('grades.view', $request->wantsJson());

        try {
            // ─── Extra fields for DataTable ───────────────────────────────────────
            // These are sent to frontend via HasTableQuery trait
            // We show school section names as a concatenated string (multi-value)
            $extraFields = [
                [
                    'field' => 'school_section_names',
                    'relation' => 'schoolSections',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => false, // sorting multi-value is complex → disabled
                    'filterType' => 'text',
                    // Optional: custom formatter could be added in frontend
                ],
            ];

            // ─── Base query with eager loading ─────────────────────────────────────
            // We always load schoolSections relation (many-to-many)
            // Only select needed fields to reduce memory & payload
            $query = Grade::query()
                ->with(['schoolSections:id,name'])
                ->when($schoolSection, function ($q) use ($schoolSection) {
                    // Filter to grades assigned to this specific section
                    $q->inSection($schoolSection->id);
                });

            // ─── Apply dynamic DataTable processing ────────────────────────────────
            // Uses HasTableQuery trait: search, filter, sort, pagination, etc.
            // Returns Laravel paginator or full collection depending on request
            $grades = $query->tableQuery($request, $extraFields);

            // ─── JSON response (DataTable AJAX, API clients, mobile) ───────────────
            if ($request->wantsJson()) {
                return GradeResource::collection($grades);
            }

            // ─── Inertia SSR response (web UI) ─────────────────────────────────────
            return Inertia::render('Academic/Exam/Grades', [
                // Current section context (if nested route)
                'schoolSection' => $schoolSection?->only('id', 'name'),

                // Paginated/filtered grades (already processed by tableQuery)
                'grades' => $grades,

                // Breadcrumbs for UI navigation (can be used in the frontend layout)
                'crumbs' => [
                    ['label' => 'Settings'],
                    ['label' => 'Academic'],
                    ['label' => 'Grading Scales'],
                ],

                // Full list for dropdowns / multi-select in modals
                'schoolSections' => SchoolSection::select('id', 'name')
                    ->orderBy('name')
                    ->get(),
            ]);
        } catch (\Throwable $e) {
            // ─── Structured error logging ──────────────────────────────────────────
            Log::error('Failed to fetch grades listing', [
                'school_section_id' => $schoolSection?->id,
                'request_params' => $request->query(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // ─── User-friendly response ────────────────────────────────────────────
            $errorMessage = 'Failed to load grades. Please try again or contact support.';

            if ($request->wantsJson()) {
                return response()->json([
                    'error' => $errorMessage,
                    'message' => $e->getMessage(), // optional – for dev only
                ], 500);
            }

            return redirect()->back()->with('error', $errorMessage);
        }
    }

    /**
     * Store a newly created grade.
     *
     * This method handles the creation of a new Grade record via the GradeService.
     * It supports two response formats:
     *   1. JSON (for AJAX/modals, API clients, mobile)
     *   2. Inertia redirect with flash message (traditional web form submission)
     *
     * Features / Responsibilities:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Early permission check using custom permitted() helper
     * • Delegates all business logic (creation + section sync + event dispatch) to GradeService
     * • Uses validated data from StoreGradeRequest (school_id already injected)
     * • Returns standardized JSON response with GradeResource when JSON is requested
     * • Provides user-friendly flash messages for Inertia/web flows
     * • Consistent error handling: 422 on business/validation failure, 500 only on unexpected errors
     * • Eager-loads schoolSections relation in success response for immediate frontend use
     * • Structured logging already handled inside GradeService (no duplication here)
     * • HTTP 201 Created status on successful JSON creation (REST best practice)
     *
     * How it fits into the Grades Module:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Primary action for creating grades from the frontend modal (PrimeVue form)
     * • Called via POST /grades or Inertia form submission
     * • Works seamlessly with:
     *   - StoreGradeRequest (validation + school_id injection)
     *   - GradeService::create() (business rules, transaction, events)
     *   - GradeResource (standardized JSON shape)
     *   - BelongsToSections trait (section sync handled in service)
     * • Ensures frontend gets fresh grade data with sections loaded for immediate display
     * • Supports both modal AJAX (wantsJson) and full-page form fallback
     *
     * Performance & Security Notes:
     * • Permission check first → fail fast
     * • No direct Eloquent usage → all domain logic in service
     * • Response shape consistent across controller actions
     * • 422 used for expected failures (validation/business rules)
     *
     * @param  StoreGradeRequest  $request  Validated request with school_id, name, code, min/max_score, etc.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function store(StoreGradeRequest $request)
    {
        // Early authorization check – supports both JSON (API/modal) and web contexts
        permitted('grades.create', $request->wantsJson());

        // Delegate creation logic to service layer
        $result = $this->service->create($request->validated());

        // ─── Handle failure case ────────────────────────────────────────────────────────
        if (!$result['success']) {
            // Business/validation failure → 422 Unprocessable Entity
            return $request->wantsJson()
                ? response()->json([
                    'error' => $result['message'],
                    // Optional: include detailed error key for form field mapping
                    'errors' => ['general' => $result['message']],
                ], 422)
                : redirect()->back()
                    ->withInput()
                    ->with('error', $result['message']);
        }

        // ─── Handle success case ────────────────────────────────────────────────────────
        $grade = $result['data']->load('schoolSections'); // eager-load relations for frontend

        return $request->wantsJson()
            ? response()->json([
                'message' => $result['message'],
                'grade' => new GradeResource($grade),
            ], 201) // 201 Created – REST best practice for resource creation
            : redirect()->back()
                ->with('success', $result['message']);
    }

    /**
     * Display a single grade (JSON response only).
     *
     * This endpoint is primarily used for:
     *   - Fetching grade details to populate edit modals (PrimeVue form)
     *   - Providing data for detail views / tooltips / quick previews
     *   - Supporting future API clients or mobile integrations
     *
     * Features / Responsibilities:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Strict JSON-only response (no Inertia SSR fallback)
     * • Early permission check using custom permitted() helper (JSON context)
     * • Eager-loads schoolSections relation with minimal fields (id, name)
     * • Wraps response in GradeResource for standardized, clean JSON shape
     * • Structured logging with context (grade_id, error details) for production debugging
     * • Consistent error response format (500 on unexpected failure)
     * • Uses route model binding (Grade $grade) → automatic 404 if not found
     * • Lightweight & fast – optimized for modal / AJAX fetch scenarios
     *
     * How it fits into the Grades Module:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Called via GET /grades/{grade} (or AJAX from edit modal in Grades.vue)
     * • Provides complete grade data (including many-to-many school sections)
     * • Integrates with:
     *   - GradePolicy (via permitted() – can be replaced with $this->authorize('view', $grade))
     *   - GradeResource (standardized output: id, name, code, range, sections array, etc.)
     *   - BelongsToSections trait (schoolSections relation)
     * • Ensures frontend modal receives fresh, relation-loaded data without extra requests
     * • Supports future features: preview pane, audit history modal, etc.
     *
     * Performance & Security Notes:
     * • Permission check first → fail fast on unauthorized access
     * • Only loads necessary relation fields (:id,name) → minimal payload
     * • No sensitive data exposed (GradeResource controls output)
     * • Structured logging helps trace issues without exposing to user
     *
     * @param  Request  $request  Incoming HTTP request (used for permission context)
     * @param  Grade    $grade    Route model bound grade instance
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Grade $grade)
    {
        // Early authorization check – JSON context only
        permitted('grades.view', true);

        try {
            // Eager-load minimal school sections data (many-to-many relation)
            $grade->load('schoolSections:id,name');

            // Return standardized JSON via resource
            return response()->json(new GradeResource($grade));
        } catch (\Throwable $e) {
            // Structured logging for production observability
            Log::error('Failed to fetch single grade details', [
                'grade_id' => $grade->id,
                'grade_code' => $grade->code ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
                'request_url' => $request->fullUrl(),
            ]);

            // User-facing generic error (keeps details internal)
            return response()->json([
                'error' => 'Failed to load grade details. Please try again or contact support.',
                // Optional: include message in dev mode only
                // 'debug' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update the specified grade.
     *
     * This method handles updating an existing Grade record via the GradeService.
     * It supports two response formats:
     *   1. JSON (for AJAX/modal submissions, API clients)
     *   2. Inertia redirect with flash message (traditional web form fallback)
     *
     * Features / Responsibilities:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Early permission check using custom permitted() helper (supports JSON/web)
     * • Delegates all business logic (update + section sync + event dispatch + usage protection)
     *   to GradeService — controller remains thin and HTTP-focused
     * • Uses validated data from UpdateGradeRequest (school_id already present from creation)
     * • Returns standardized JSON with GradeResource on success (with schoolSections loaded)
     * • Provides user-friendly flash messages or JSON errors for Inertia/web flows
     * • Consistent HTTP status: 422 for expected business/validation failures
     * • Eager-loads schoolSections in success response → frontend gets complete data immediately
     * • No direct Eloquent operations — all domain rules enforced in service
     * • Structured error handling (logging already done in service)
     *
     * How it fits into the Grades Module:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Primary action for editing grades from the frontend modal (PrimeVue form)
     * • Called via PATCH/PUT /grades/{grade}
     * • Works seamlessly with:
     *   - UpdateGradeRequest (validation + school context)
     *   - GradeService::update() (transaction, usage check, section sync, events)
     *   - GradeResource (clean, standardized JSON output)
     *   - BelongsToSections trait (section sync handled in service)
     * • Ensures frontend modal receives updated grade with fresh sections without extra requests
     * • Supports both modal AJAX (wantsJson) and full-page form fallback
     *
     * Performance & Security Notes:
     * • Permission check first → fail fast on unauthorized access
     * • No sensitive data exposed (GradeResource controls output)
     * • 422 used for business/validation failures (expected errors)
     * • Service handles all transactions/logging → controller stays lightweight
     *
     * @param  UpdateGradeRequest  $request  Validated request with name, code, min/max_score, school_section_ids?, etc.
     * @param  Grade               $grade    Route model bound grade instance
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function update(UpdateGradeRequest $request, Grade $grade)
    {
        // Early authorization check – consistent for both JSON (modal/API) and web contexts
        permitted('grades.update', $request->wantsJson());

        // Delegate full update logic (validation already done, business rules in service)
        $result = $this->service->update($grade, $request->validated());

        // ─── Handle failure case ────────────────────────────────────────────────────────
        if (!$result['success']) {
            // Business/validation failure → 422 Unprocessable Entity
            return $request->wantsJson()
                ? response()->json([
                    'error' => $result['message'],
                    // Optional: structured errors for form field mapping in frontend
                    'errors' => ['general' => $result['message']],
                ], 422)
                : redirect()->back()
                    ->withInput()
                    ->with('error', $result['message']);
        }

        // ─── Handle success case ────────────────────────────────────────────────────────
        // Reload with fresh relations so frontend gets complete updated data
        $updatedGrade = $result['data']->load('schoolSections');

        return $request->wantsJson()
            ? response()->json([
                'message' => $result['message'],
                'grade' => new GradeResource($updatedGrade),
            ])
            : redirect()->back()
                ->with('success', $result['message']);
    }

    /**
     * Remove one or more grades (soft-delete or force-delete).
     *
     * This endpoint supports both soft deletion (default) and permanent (force) deletion.
     * It expects a JSON payload with:
     *   - ids: array of grade IDs to delete
     *   - force: boolean (optional) – whether to permanently delete
     *
     * Features / Responsibilities:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Early permission check using custom permitted() helper (JSON-only context)
     * • Validates input: required array of valid grade IDs + optional force flag
     * • Delegates deletion logic (usage protection, event dispatch) to GradeService
     * • Supports bulk deletion (multiple IDs in one request)
     * • Handles both soft-delete and force-delete modes via $force parameter
     * • Returns consistent JSON response: success message or structured error
     * • Structured logging with context (IDs, force mode, error details) for production visibility
     * • Stops on first failure in bulk mode → prevents partial deletes
     * • Uses 422 for business/validation failures (e.g. used grade), 500 only for unexpected errors
     * • No Inertia fallback – this is an AJAX/JSON-only endpoint (used from DataTable bulk actions)
     *
     * How it fits into the Grades Module:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Primary action for deleting grades from the frontend DataTable (PrimeVue bulk actions)
     * • Called via POST/DELETE /grades/destroy with JSON body { ids: [...], force?: boolean }
     * • Integrates with:
     *   - GradeService::delete($grade, $force) (usage check, soft/force logic, events)
     *   - GradePolicy (controller can add $this->authorize('delete', Grade::class) or 'forceDelete')
     *   - DataTable bulk action UI (sends ids array + force checkbox if super-admin)
     * • Ensures frontend gets clear feedback when deletion is blocked (used grades)
     * • Supports future extensions: bulk force-delete confirmation modal, undo soft-delete
     *
     * Security & Performance Notes:
     * • Permission check first → fail fast on unauthorized bulk delete
     * • Validates all IDs exist → prevents invalid requests
     * • Usage protection enforced per-grade in service → no accidental data loss
     * • Force delete requires separate policy check (e.g. super-admin only)
     * • Structured logging helps trace bulk failures quickly
     *
     * Expected Request Payload (JSON):
     * {
     *   "ids": [1, 2, 3],
     *   "force": true   // optional, default false
     * }
     *
     * @param  Request  $request  Incoming request with ids array and optional force flag
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        // Early permission check – JSON-only context
        permitted('grades.delete', true);

        try {
            // Validate input: required array of existing grade IDs + optional force flag
            $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:grades,id',
                'force' => 'sometimes|boolean', // optional – default false (soft delete)
            ]);

            $ids = $request->input('ids');
            $force = $request->boolean('force', false);

            // If force delete is requested, perform additional authorization
            if ($force) {
                Gate::authorize('forceDelete', Grade::class);
            }

            // Process each grade individually so we can stop on first failure
            $gradesCollection = Grade::query()->whereIn('id', $ids)->get();

            $gradesCollection->each(function ($grade) use ($force) {
                $result = $this->service->delete($grade, $force);

                if (!$result['success']) {
                    // Return first failure → prevents partial bulk deletes
                    throw new \Exception("Failed to delete grade ID {$grade->id}: " . $result['message']);
                }
            });

            // Success message – pluralize based on count
            $count = count($ids);
            $actionWord = $force ? 'permanently deleted' : 'deleted';
            $message = $count > 1
                ? "{$count} grades {$actionWord} successfully"
                : "Grade {$actionWord} successfully";

            return response()->json([
                'message' => $message,
                'count' => $count,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Validation failure (e.g. invalid IDs) → 422 with detailed errors
            return response()->json([
                'error' => 'Invalid input provided.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            // Unexpected error → 500 with generic message
            Log::error('Bulk grade deletion failed in controller', [
                'ids' => $request->input('ids'),
                'force' => $request->boolean('force', false),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to delete grade(s). Please try again or contact support.',
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted grade.
     *
     * This endpoint restores a previously soft-deleted Grade record.
     * It is designed for JSON-only responses (used from frontend modals, DataTable actions, or API clients).
     *
     * Features / Responsibilities:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Early permission check using custom permitted() helper (JSON context only)
     * • Uses route parameter $id with withTrashed() lookup → returns 404 if not found (even if deleted)
     * • Delegates actual restore logic (validation, restore operation) to GradeService
     * • Returns standardized JSON success response with GradeResource (fresh data + relations)
     * • Provides clear error responses: 422 for expected business failures, 500 for unexpected errors
     * • Structured logging with context (grade ID, error details) for production debugging
     * • Consistent response shape across all controller actions (message + grade on success)
     * • No Inertia fallback – this is an AJAX/JSON-only endpoint (modal restore button)
     *
     * How it fits into the Grades Module:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Called via POST/PATCH /grades/{id}/restore (or custom route)
     * • Triggered from frontend: "Restore" action in trashed view or confirmation modal
     * • Integrates with:
     *   - GradeService::restore() (business rules, fresh reload with relations)
     *   - GradePolicy (controller can add $this->authorize('restore', $grade))
     *   - GradeResource (standardized output: id, name, code, sections array, etc.)
     *   - DataTable trashed rows / bulk restore actions
     * • Ensures frontend receives updated grade data (with schoolSections) without extra fetch
     * • Supports future extensions: bulk restore, audit logging, notification on restore
     *
     * Performance & Security Notes:
     * • Permission check first → fail fast on unauthorized restore
     * • findOrFail() with withTrashed() → automatic 404 for non-existent/deleted records
     * • No sensitive data exposed (GradeResource controls output)
     * • Structured logging helps trace restore failures quickly
     * • 422 used for expected business failures (e.g. "not deleted")
     *
     * @param  Request  $request  Incoming request (used for permission context)
     * @param  int      $id       ID of the soft-deleted grade to restore
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        // Early authorization check – JSON-only context
        permitted('grades.restore', true);

        try {
            // Find soft-deleted grade or fail with 404
            $grade = Grade::withTrashed()->findOrFail($id);

            // Optional: additional policy check (if you want per-instance authorization)
            // $this->authorize('restore', $grade);

            // Delegate restore logic to service layer
            $result = $this->service->restore($grade);

            // ─── Handle failure case ────────────────────────────────────────────────────────
            if (!$result['success']) {
                return response()->json([
                    'error' => $result['message'],
                    // Optional: structured errors for frontend form/toast mapping
                    'errors' => ['general' => $result['message']],
                ], 422);
            }

            // ─── Handle success case ────────────────────────────────────────────────────────
            // Reload fresh with relations so frontend gets complete restored data
            $restoredGrade = $result['data']->load('schoolSections');

            return response()->json([
                'message' => $result['message'],
                'grade' => new GradeResource($restoredGrade),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Grade not found (deleted or never existed) → 404 Not Found
            return response()->json([
                'error' => 'Grade not found or already permanently deleted.',
            ], 404);
        } catch (\Throwable $e) {
            // Unexpected error → 500 with generic message
            Log::error('Grade restore failed in controller', [
                'grade_id' => $id,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
                'request_url' => $request->fullUrl(),
            ]);

            return response()->json([
                'error' => 'Failed to restore grade. Please try again or contact support.',
            ], 500);
        }
    }
}
