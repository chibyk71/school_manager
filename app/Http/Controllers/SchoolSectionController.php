<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkSchoolSectionRequest;
use App\Http\Requests\StoreFromTemplatesRequest;
use App\Http\Requests\StoreSchoolSectionRequest;
use App\Http\Requests\UpdateSchoolSectionRequest;
use App\Http\Resources\SchoolSectionResource;
use App\Models\SchoolSection;
use App\Services\SchoolSectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * SchoolSectionController — Production-Ready
 *
 * Thin HTTP adapter for all SchoolSection operations. Every domain concern
 * (business rules, transactions, events, cache invalidation) lives in
 * SchoolSectionService. This controller only handles:
 *   - Routing requests to the correct service method
 *   - Authorization via Gate::authorize() (Policy auto-resolution)
 *   - Response format selection (Inertia vs JSON)
 *   - Flash messaging on redirects
 *
 * ── No Permission Props ──────────────────────────────────────────────────
 * Permission flags are NOT passed from this controller. The authenticated
 * user's full permissions are shared on every Inertia response via
 * HandleInertiaRequests middleware. The usePermissions() composable on
 * the frontend reads them from page.props.auth.permissions.
 * This keeps controllers clean and avoids duplicating permission logic.
 *
 * ── Response Format ──────────────────────────────────────────────────────
 * index()              → Inertia (page load) or JSON (DataTable axios refetch)
 * show()               → Inertia always
 * store()              → RedirectResponse (Inertia redirect to index)
 * storeFromTemplates() → RedirectResponse
 * update()             → RedirectResponse
 * destroy()            → JsonResponse (bulk composable expects JSON)
 * restore()            → JsonResponse (bulk composable expects JSON)
 * forceDestroy()       → JsonResponse
 * bulkToggle()         → JsonResponse
 * reorder()            → JsonResponse (drag-drop, no page navigation)
 * templates()          → JsonResponse always
 * options()            → JsonResponse always
 *
 * ── Bulk Operations ──────────────────────────────────────────────────────
 * The frontend useDeleteResource and useRestoreResource composables always
 * send an array of IDs even for single-record operations. All bulk methods
 * therefore handle both single and multiple records uniformly — no
 * separate "delete one" vs "delete many" methods needed.
 *
 * ── scopeTableQuery Usage ────────────────────────────────────────────────
 * Called as SchoolSection::query()->tableQuery($request, $extraFields)
 * matching the HasTableQuery trait signature. Extra fields add computed
 * columns (class_levels_count, students_count) to the column definitions
 * returned to the DataTable.
 *
 * ── Authorization ────────────────────────────────────────────────────────
 * Gate::authorize() resolves SchoolSectionPolicy automatically via
 * Laravel's policy auto-discovery (model registered in AuthServiceProvider).
 * BelongsToSchool global scope handles cross-tenant isolation at query level.
 * Policy's belongsToCurrentSchool() check provides defense-in-depth for
 * instance-level operations.
 *
 * @see App\Services\SchoolSectionService
 * @see App\Policies\SchoolSectionPolicy
 * @see App\Models\SchoolSection
 * @see App\Http\Requests\StoreSchoolSectionRequest
 * @see App\Http\Requests\UpdateSchoolSectionRequest
 * @see App\Http\Requests\BulkSchoolSectionRequest
 * @see App\Http\Requests\StoreFromTemplatesRequest
 */
class SchoolSectionController extends Controller
{
    public function __construct(
        private readonly SchoolSectionService $service
    ) {
    }

    // ──────────────────────────────────────────────────────────────────────
    // READ
    // ──────────────────────────────────────────────────────────────────────

    /**
     * List all sections for the current school.
     *
     * Serves two consumers from a single method:
     *
     * 1. Inertia page load (browser navigation / SPA route change):
     *    Returns Inertia::render() with initialData (first query result set),
     *    column definitions from HasTableQuery, and globalFilterables for
     *    the AdvancedDataTable search field binding.
     *
     * 2. AdvancedDataTable axios refetch (sort / filter / paginate / search):
     *    Detects wantsJson() — returns raw scopeTableQuery result as JSON.
     *    The useDataTable composable on the frontend consumes this shape.
     *
     * Both paths call SchoolSection::query()->tableQuery() identically.
     * No code duplication. The query result shape is the same for both.
     *
     * The HasTableQuery trait handles:
     *   - Global search across filterable text columns
     *   - Laravel Purity filters and sorts from request params
     *   - Soft-delete awareness via ?trashed=1 (Purity's trashed() scope)
     *   - Hybrid mode: full_load for client-side or windowed for server-side
     *   - Column definitions with visibility, filterType, sortable flags
     *
     * Extra fields add computed columns (class_levels_count, students_count)
     * to the column definition array returned by the trait.
     *
     * @param  Request  $request
     * @return InertiaResponse|JsonResponse
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        Gate::authorize('viewAny', SchoolSection::class);

        $result = SchoolSection::query()
            ->withCount(['classLevels', 'students'])
            ->tableQuery($request, [
                'class_levels_count' => [
                    'header' => 'Class Levels',
                    'sortable' => true,
                    'filterable' => false,
                    'filterType' => 'number',
                ],
                'students_count' => [
                    'header' => 'Students',
                    'sortable' => true,
                    'filterable' => false,
                    'filterType' => 'number',
                ],
            ]);

        // AdvancedDataTable axios refetch — return raw JSON
        if ($request->wantsJson()) {
            return response()->json($result);
        }

        // Inertia page load — render full page with initial data
        return Inertia::render('Settings/Sections/Index', [
            'initialData' => $result['data'],
            'totalRecords' => $result['totalRecords'],
            'columns' => $result['columns'],
            'globalFilterables' => $result['globalFilterables'] ?? [],
        ]);
    }

    /**
     * Show a single section detail page.
     *
     * Loads classLevels relationship for the detail view panel.
     * Student counts are loaded lazily via their own DataTable on frontend.
     *
     * @param  SchoolSection  $schoolSection  Route model binding
     * @return InertiaResponse
     */
    public function show(SchoolSection $schoolSection): InertiaResponse
    {
        Gate::authorize('view', $schoolSection);

        $schoolSection->loadCount(['classLevels', 'students']);

        return Inertia::render('Settings/Sections/Show', [
            'section' => new SchoolSectionResource($schoolSection),
        ]);
    }

    /**
     * Return available config templates as JSON.
     *
     * Used by SectionFromTemplatesModal to display and select templates.
     * Marks each template as available or already created for this school
     * so the frontend can disable already-existing options without
     * making a separate existence-check request.
     *
     * One query fetches all existing names — O(1) DB calls regardless
     * of template count.
     *
     * @return JsonResponse
     */
    public function templates(): JsonResponse
    {
        Gate::authorize('create', SchoolSection::class);

        $allTemplates = config('school_section_templates', []);
        $templateNames = array_column(array_values($allTemplates), 'name');

        $existingNames = SchoolSection::whereIn('name', $templateNames)
            ->pluck('name')
            ->toArray();

        $templates = collect($allTemplates)
            ->map(fn(array $tpl, string $key) => array_merge($tpl, [
                'key' => $key,
                'available' => !in_array($tpl['name'], $existingNames, strict: true),
            ]))
            ->values();

        return response()->json([
            'templates' => $templates,
            'available_count' => $templates->where('available', true)->count(),
        ]);
    }

    /**
     * Return lightweight section list for dropdowns and select components.
     *
     * Used by SectionPicker.vue, AsyncSelect, and any component that needs
     * id + name without full model data. Only returns active sections,
     * ordered by sort_order.
     *
     * Supports ?search= and ?q= query params for AsyncSelect live filtering
     * (matches name, display_name, short_code).
     *
     * Response shape matches useAsyncOptions composable expectations:
     *   { data: [...], total: N }
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function options(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', SchoolSection::class);

        $query = SchoolSection::active()
            ->ordered()
            ->select(['id', 'name', 'display_name', 'short_code']);

        $term = $request->input('search') ?? $request->input('q');

        if (filled($term)) {
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('display_name', 'like', "%{$term}%")
                    ->orWhere('short_code', 'like', "%{$term}%");
            });
        }

        $options = $query->get()->map(fn(SchoolSection $s) => [
            'id' => $s->id,
            'name' => $s->display_name ?? $s->name,
            'short_code' => $s->short_code,
            'label' => "{$s->display_name} ({$s->short_code})",
            'value' => $s->id,
        ]);

        return response()->json([
            'data' => $options,
            'total' => $options->count(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // WRITE — Single Record
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Create a single section from validated form data.
     *
     * StoreSchoolSectionRequest::authorize() checks sections.create permission.
     * No Gate::authorize() needed — Form Request authorization is sufficient.
     *
     * school_id is never in the validated payload (prohibited in Form Request).
     * BelongsToSchool boot hook assigns it automatically on create.
     *
     * @param  StoreSchoolSectionRequest  $request
     * @return RedirectResponse
     */
    public function store(StoreSchoolSectionRequest $request): RedirectResponse
    {
        $section = $this->service->createOne($request->validated());

        return redirect()
            ->route('settings.sections.index')
            ->with('success', "The \"{$section->display_name}\" section has been created.");
    }

    /**
     * Create multiple sections from config-defined templates.
     *
     * StoreFromTemplatesRequest handles authorization (sections.create)
     * and all conflict validation (active + soft-deleted conflicts)
     * before this method is reached.
     *
     * $request->resolvedTemplates() returns the already-filtered,
     * config-canonical template data for submitted keys only.
     *
     * @param  StoreFromTemplatesRequest  $request
     * @return RedirectResponse
     */
    public function storeFromTemplates(StoreFromTemplatesRequest $request): RedirectResponse
    {
        $created = $this->service->createFromTemplates(
            $request->resolvedTemplates()
        );

        $count = $created->count();
        $names = $created->pluck('display_name')->join(', ', ' and ');

        return redirect()
            ->route('settings.sections.index')
            ->with('success', "{$count} section(s) created from templates: {$names}.");
    }

    /**
     * Update a single section.
     *
     * Policy verifies the section belongs to the current school AND the
     * user has sections.update permission.
     *
     * Source mutation (template → custom on tracked field changes) is
     * handled automatically by SchoolSectionObserver — no controller logic needed.
     *
     * UpdateSchoolSectionRequest prohibits school_id and source fields
     * so they can never be changed via this endpoint.
     *
     * @param  UpdateSchoolSectionRequest  $request
     * @param  SchoolSection               $schoolSection
     * @return RedirectResponse
     */
    public function update(
        UpdateSchoolSectionRequest $request,
        SchoolSection $schoolSection
    ): RedirectResponse {
        Gate::authorize('update', $schoolSection);

        $section = $this->service->update($schoolSection, $request->validated());

        return redirect()
            ->route('settings.sections.index')
            ->with('success', "The \"{$section->display_name}\" section has been updated.");
    }

    // ──────────────────────────────────────────────────────────────────────
    // WRITE — Bulk Operations
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Soft-delete one or more sections.
     *
     * The useDeleteResource composable on the frontend always sends an
     * array of IDs (even for single-record delete), so this method handles
     * both single and bulk uniformly.
     *
     * BulkSchoolSectionRequest validates ids[], enforces max:250, and
     * checks sections.delete permission in authorize(). Action must be 'delete'.
     *
     * Returns JsonResponse — the composable expects this shape:
     *   { message: string }  on success (2xx)
     *   { message: string }  on failure (4xx/5xx)
     *
     * @param  BulkSchoolSectionRequest  $request
     * @return JsonResponse
     */
    public function destroy(BulkSchoolSectionRequest $request): JsonResponse
    {
        $deleted = $this->service->bulkDelete($request->validated('ids'));

        return response()->json([
            'message' => "{$deleted} section(s) deleted successfully.",
            'count' => $deleted,
        ]);
    }

    /**
     * Restore one or more soft-deleted sections.
     *
     * useRestoreResource composable sends array of IDs.
     * BulkSchoolSectionRequest checks sections.restore permission.
     * Action must be 'restore'.
     *
     * @param  BulkSchoolSectionRequest  $request
     * @return JsonResponse
     */
    public function restore(BulkSchoolSectionRequest $request): JsonResponse
    {
        $restored = $this->service->bulkRestore($request->validated('ids'));

        return response()->json([
            'message' => "{$restored} section(s) restored successfully.",
            'count' => $restored,
        ]);
    }

    /**
     * Permanently delete one or more soft-deleted sections.
     *
     * Separate method from destroy() because:
     *   1. Requires a stricter permission (sections.force-delete)
     *   2. Only operates on already-soft-deleted records
     *   3. The service throws ValidationException if non-trashed IDs
     *      are submitted — clear, explicit contract
     *
     * Inline validation used (not BulkSchoolSectionRequest) because
     * forceDestroy requires sections.force-delete permission while
     * BulkSchoolSectionRequest maps action='delete' to sections.delete.
     * Using the wrong permission check would be a silent security bug.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function forceDestroy(Request $request): JsonResponse
    {
        Gate::authorize('forceDelete', SchoolSection::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:250'],
            'ids.*' => ['required', 'uuid'],
        ]);

        $deleted = $this->service->bulkForceDelete($validated['ids']);

        return response()->json([
            'message' => "{$deleted} section(s) permanently deleted.",
            'count' => $deleted,
        ]);
    }

    /**
     * Activate or deactivate one or more sections.
     *
     * BulkSchoolSectionRequest validates action = 'toggle' and requires
     * is_active (boolean). Checks sections.update permission.
     *
     * @param  BulkSchoolSectionRequest  $request
     * @return JsonResponse
     */
    public function bulkToggle(BulkSchoolSectionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $isActive = (bool) $validated['is_active'];

        $affected = $this->service->bulkToggleStatus(
            $validated['ids'],
            $isActive
        );

        $state = $isActive ? 'activated' : 'deactivated';

        return response()->json([
            'message' => "{$affected} section(s) {$state} successfully.",
            'count' => $affected,
        ]);
    }

    /**
     * Reorder sections by assigning new sort_order positions.
     *
     * Expects ordered array of IDs. Service assigns sort_order = (index+1)*10.
     * Returns JsonResponse — reorder is triggered by drag-and-drop which
     * should not cause full page navigation.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function reorder(Request $request): JsonResponse
    {
        Gate::authorize('update', SchoolSection::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:250'],
            'ids.*' => ['required', 'uuid'],
        ]);

        $updated = $this->service->reorder($validated['ids']);

        return response()->json([
            'message' => "Section order updated successfully.",
            'updated' => $updated,
        ]);
    }
}
