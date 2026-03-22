<?php

/**
 * ClassLevelController
 *
 * Handles all HTTP concerns for ClassLevel management.
 *
 * Route structure this controller serves:
 * ─────────────────────────────────────────────────────────────────────────────
 * Nested (section-scoped):
 *   GET    /sections/{section}/class-levels              → index()
 *   POST   /sections/{section}/class-levels              → store()
 *   PATCH  /sections/{section}/class-levels/{classLevel} → update()
 *   DELETE /sections/{section}/class-levels              → destroy()
 *   POST   /sections/{section}/class-levels/restore      → restore()
 *   DELETE /sections/{section}/class-levels/force        → forceDelete()
 *   POST   /sections/{section}/class-levels/bulk-generate → bulkGenerate()
 *   PATCH  /sections/{section}/class-levels/reorder      → reorder()
 *
 * Global (settings view):
 *   GET    /settings/academic/class-levels               → globalIndex()
 *
 * Preset data (for BulkGenerateModal cascade select):
 *   GET    /sections/{section}/class-levels/presets      → presets()
 *
 * Controller responsibilities:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Route model binding resolution
 * - Authorization via policy
 * - Delegating business logic to ClassLevelService
 * - Shaping responses via ClassLevelResource
 * - Returning Inertia responses for page routes
 * - Returning JSON responses for DataTable/API routes
 *
 * What this controller does NOT do:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Business logic (in ClassLevelService)
 * - Validation (in FormRequests)
 * - Response field shaping (in ClassLevelResource)
 */

namespace App\Http\Controllers;

use App\Http\Requests\StoreClassLevelRequest;
use App\Http\Requests\UpdateClassLevelRequest;
use App\Http\Resources\ClassLevelResource;
use App\Models\Academic\ClassLevel;
use App\Models\SchoolSection;
use App\Services\ClassLevelService;
use App\Support\ClassLevelPresets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClassLevelController extends Controller
{
    public function __construct(
        protected ClassLevelService $service
    ) {
    }

    // ─── Section-scoped: List ─────────────────────────────────────────────────

    /**
     * Return paginated/filtered class levels for a specific section.
     *
     * Used by ClassLevelsTab.vue DataTable via Axios (JSON, not Inertia).
     * Supports the full HasTableQuery feature set: search, filter, sort,
     * pagination, windowed prefetch, and full-load (client-side) mode.
     *
     * Includes withCount so is_deletable is computed correctly in the resource.
     * Includes withTrashed when the request carries ?trashed=1 (trash toggle).
     */
    public function index(Request $request, SchoolSection $section): JsonResponse
    {
        Gate::authorize('viewAny', ClassLevel::class);

        $query = ClassLevel::forSection($section->id)
            ->withCount('classSections');

        // Trash toggle — matches the pattern used across your DataTables
        if ($request->boolean('trashed')) {
            $query->onlyTrashed();
        }

        $result = (new ClassLevel)->scopeTableQuery(
            $query,
            $request,
            [
                'section' => [
                    'header' => 'Section',
                    'filterable' => false,
                    'sortable' => false,
                    'hidden' => true, // section is known from route context
                ],
            ]
        );

        return response()->json([
            ...$result,
            'data' => ClassLevelResource::collection($result['data']),
        ]);
    }

    // ─── Global settings view: List ───────────────────────────────────────────

    /**
     * Inertia page: global class levels view under Settings → Academic.
     *
     * Shows all class levels across all sections for the current school.
     * Section name is included via eager-loaded schoolSection relation.
     * Admin can filter by section using the section dropdown on the page.
     */
    public function globalIndex(Request $request): Response
    {
        Gate::authorize('viewAny', ClassLevel::class);

        $query = ClassLevel::with('schoolSection')
            ->withCount('classSections');

        // Optional section filter from the dropdown on the page
        if ($request->filled('section_id')) {
            $query->forSection($request->input('section_id'));
        }

        // Trash toggle
        if ($request->boolean('trashed')) {
            $query->onlyTrashed();
        }

        $result = (new ClassLevel)->scopeTableQuery($query, $request);

        // Sections list for the filter dropdown
        $sections = SchoolSection::ordered()
            ->get(['id', 'name']);

        return Inertia::render('Settings/Academic/ClassLevels', [
            'classLevels' => [
                ...$result,
                'data' => ClassLevelResource::collection($result['data']),
            ],
            'sections' => $sections,
            'filters' => $request->only(['section_id', 'trashed']),
        ]);
    }

    // ─── Preset tree ──────────────────────────────────────────────────────────

    /**
     * Return the preset tree for the BulkGenerateModal cascade select.
     *
     * Called once when BulkGenerateModal mounts. Response is small and
     * static — no DB queries, no pagination. Can be cached aggressively
     * in future if needed.
     */
    public function presets(): JsonResponse
    {
        Gate::authorize('create', ClassLevel::class);

        return response()->json([
            'presets' => ClassLevelPresets::toTree(),
        ]);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    /**
     * Create a single class level within the given section.
     *
     * Validation: StoreClassLevelRequest (name unique per section,
     * sequence unique per section, section ownership verified).
     * Business logic: ClassLevelService::create().
     */
    public function store(
        StoreClassLevelRequest $request,
        SchoolSection $section
    ): JsonResponse {
        Gate::authorize('create', ClassLevel::class);

        $classLevel = $this->service->create($section, $request->validated());

        return response()->json([
            'message' => "Class level \"{$classLevel->name}\" created successfully.",
            'class_level' => new ClassLevelResource($classLevel),
        ], 201);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    /**
     * Update an existing class level.
     *
     * Validation: UpdateClassLevelRequest (partial update, school_section_id
     * is immutable — stripped in the request's prepareForValidation).
     * Business logic: ClassLevelService::update() (guards deactivation if
     * students are enrolled).
     */
    public function update(
        UpdateClassLevelRequest $request,
        SchoolSection $section,
        ClassLevel $classLevel
    ): JsonResponse {
        Gate::authorize('update', $classLevel);

        // Ensure the level actually belongs to the route section
        abort_if(
            $classLevel->school_section_id !== $section->id,
            404,
            'Class level not found in this section.'
        );

        $updated = $this->service->update($classLevel, $request->validated());

        return response()->json([
            'message' => "Class level \"{$updated->name}\" updated successfully.",
            'class_level' => new ClassLevelResource($updated),
        ]);
    }

    // ─── Destroy (bulk soft-delete) ───────────────────────────────────────────

    /**
     * Soft-delete one or more class levels.
     *
     * Accepts an array of IDs in the request body (same pattern as your
     * other bulk delete endpoints). All IDs are validated to belong to
     * the route section before the service is called.
     *
     * Business guards in service:
     * - Cannot delete if class sections (streams) are attached
     * - Cannot delete if students are enrolled
     */
    public function destroy(
        Request $request,
        SchoolSection $section
    ): JsonResponse {
        Gate::authorize('delete', ClassLevel::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [
                'required',
                'uuid',
                // Verify every ID belongs to this section
                Rule::exists('class_levels', 'id')
                    ->where('school_section_id', $section->id)
                    ->whereNull('deleted_at'),
            ],
        ]);

        $deleted = $this->service->delete($validated['ids']);

        return response()->json([
            'message' => "{$deleted} class level(s) deleted successfully.",
            'deleted' => $deleted,
        ]);
    }

    // ─── Restore ──────────────────────────────────────────────────────────────

    /**
     * Restore one or more soft-deleted class levels.
     *
     * Business guards in service:
     * - Checks name is still available in section (no conflict with new levels)
     * - Checks sequence is still available
     */
    public function restore(
        Request $request,
        SchoolSection $section
    ): JsonResponse {
        Gate::authorize('restore', ClassLevel::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [
                'required',
                'uuid',
                // Must exist in trash and belong to this section
                Rule::exists('class_levels', 'id')
                    ->where('school_section_id', $section->id)
                    ->whereNotNull('deleted_at'),
            ],
        ]);

        $restored = $this->service->restore($validated['ids']);

        return response()->json([
            'message' => "{$restored} class level(s) restored successfully.",
            'restored' => $restored,
        ]);
    }

    // ─── Force Delete ─────────────────────────────────────────────────────────

    /**
     * Permanently delete one or more soft-deleted class levels.
     *
     * Only operates on already soft-deleted records (onlyTrashed).
     * This is the hard delete — irreversible. Only available to users
     * with the forceDelete permission (typically super-admin only).
     *
     * Extra guard: even on force delete we block if class sections or
     * students are attached, because orphaned records would break
     * timetables, results, and promotion history.
     */
    public function forceDelete(
        Request $request,
        SchoolSection $section
    ): JsonResponse {
        Gate::authorize('forceDelete', ClassLevel::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [
                'required',
                'uuid',
                // Must already be soft-deleted and belong to this section
                Rule::exists('class_levels', 'id')
                    ->where('school_section_id', $section->id)
                    ->whereNotNull('deleted_at'),
            ],
        ]);

        $levels = ClassLevel::onlyTrashed()
            ->whereIn('id', $validated['ids'])
            ->get();

        $deleted = $this->service->forceDelete($levels);

        return response()->json([
            'message' => "{$deleted} class level(s) permanently deleted.",
            'deleted' => $deleted,
        ]);
    }

    // ─── Bulk Generate ────────────────────────────────────────────────────────

    /**
     * Generate class levels from a preset variant.
     *
     * Called from BulkGenerateModal.vue when admin selects a preset and
     * confirms. The preset key is validated against ClassLevelPresets::allKeys()
     * so unknown keys are rejected before reaching the service.
     *
     * When admin selects a whole group (not a specific variant), the frontend
     * sends the group's defaultKey which is already a full variant key.
     */
    public function bulkGenerate(
        Request $request,
        SchoolSection $section
    ): JsonResponse {
        Gate::authorize('bulkGenerate', ClassLevel::class);

        $validated = $request->validate([
            'preset_key' => [
                'required',
                'string',
                Rule::in(ClassLevelPresets::allKeys()),
            ],
        ]);

        $result = $this->service->bulkGenerate($section, $validated['preset_key']);

        return response()->json([
            'message' => "{$result['created']} level(s) created, {$result['skipped']} skipped (already exist).",
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'levels' => ClassLevelResource::collection($result['levels']),
        ]);
    }

    // ─── Reorder ──────────────────────────────────────────────────────────────

    /**
     * Reorder class level sequences within a section.
     *
     * Accepts an ordered array of IDs. Sequences are assigned 1, 2, 3...
     * in the order the IDs appear. All IDs must belong to the route section.
     *
     * Called when admin manually edits sequences or uses drag-to-reorder
     * (if added in a future UI iteration).
     */
    public function reorder(
        Request $request,
        SchoolSection $section
    ): JsonResponse {
        Gate::authorize('reorder', ClassLevel::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [
                'required',
                'uuid',
                Rule::exists('class_levels', 'id')
                    ->where('school_section_id', $section->id)
                    ->whereNull('deleted_at'),
            ],
        ]);

        $this->service->reorderSequences($section, $validated['ids']);

        return response()->json([
            'message' => 'Class level order updated successfully.',
        ]);
    }
}
