<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\AssignSubjectRequest;
use App\Http\Requests\Academic\BulkClassSectionRequest;
use App\Http\Requests\Academic\BulkGenerateClassSectionRequest;
use App\Http\Requests\Academic\StoreClassSectionRequest;
use App\Http\Requests\Academic\UpdateClassSectionRequest;
use App\Http\Resources\ClassSectionMinimalResource;
use App\Http\Resources\ClassSectionResource;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use App\Models\Academic\TeacherClassSectionSubject;
use App\Services\ClassSectionService;
use App\Support\ClassSectionNamePresets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ClassSectionController — HTTP layer for the ClassSection module.
 *
 * ── Responsibilities ──────────────────────────────────────────────────────────
 * This controller is a thin HTTP adapter. Every domain concern lives in
 * ClassSectionService. The controller's only jobs are:
 *   1. Authorize the request against ClassSectionPolicy
 *   2. Validate input via FormRequests
 *   3. Call the correct service method
 *   4. Shape and return the response (Inertia or JSON)
 *
 * ── Route Structure ───────────────────────────────────────────────────────────
 * All routes live under /settings/academic/class-sections with the name
 * prefix settings.academic.class-sections.*
 *
 * The index page is a STANDALONE page with Purity filter support.
 * Visiting from a ClassLevel passes ?filters[class_level_id][$eq]=uuid
 * which Purity handles automatically — no custom filter logic needed.
 *
 * Nested sub-resource routes (subject assignments, form teacher) are separate
 * endpoints on the same controller rather than a nested resource controller,
 * keeping the URL structure flat and the controller cohesive.
 *
 * ── Endpoint Map ──────────────────────────────────────────────────────────────
 * GET    /class-sections                       index()           DataTable page
 * POST   /class-sections                       store()           Create one
 * PATCH  /class-sections/{section}             update()          Edit one
 * DELETE /class-sections                       destroy()         Bulk delete (body: ids)
 * POST   /class-sections/restore               restore()         Bulk restore
 * DELETE /class-sections/force                 forceDestroy()    Bulk force-delete
 * POST   /class-sections/toggle                bulkToggle()      Activate/deactivate
 * POST   /class-sections/reorder               reorder()         Reorder sort_order
 * POST   /class-sections/bulk-generate         bulkGenerate()    Generate arms
 * GET    /class-sections/presets               presets()         Naming preset data
 * GET    /class-sections/options               options()         Minimal list (dropdowns)
 * PATCH  /class-sections/{section}/teacher     assignFormTeacher() Set/clear form teacher
 * POST   /class-sections/{section}/subjects    assignSubject()   Add subject assignment
 * DELETE /class-sections/{section}/subjects/{assignment}  removeSubject()
 * PATCH  /class-sections/{section}/subjects/{assignment}  updateSubjectRole()
 *
 * ── Response Format ───────────────────────────────────────────────────────────
 * index()    → Inertia render (page with DataTable)
 * options()  → JSON (AnonymousResourceCollection) — for AsyncSelect
 * presets()  → JSON (plain array) — for BulkGenerateModal
 * All others → JSON (ClassSectionResource or count message)
 */
class ClassSectionController extends Controller
{
    public function __construct(
        private readonly ClassSectionService $service
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // Index — Standalone DataTable Page
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Render the standalone class sections index page.
     *
     * Returns a hybrid response:
     *   - Inertia page props containing the first page of data + column definitions
     *     (for SSR and initial render without an extra API call)
     *   - The frontend DataTable then switches to client-side or server-side mode
     *     based on totalRecords vs the client-side threshold
     *
     * Filtering is driven by Laravel Purity via query string params:
     *   ?filters[class_level_id][$eq]=uuid   (pre-applied from ClassLevel link)
     *   ?filters[status][$eq]=active
     *   ?search=JSS 1A
     *
     * The `withCount` calls ensure the DataTable can show student and subject
     * assignment counts without extra queries per row.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ClassSection::class);

        $query = ClassSection::query()
            ->with(['classLevel.schoolSection', 'formTeacher'])
            ->withCount(['students', 'teacherSubjectAssignments']);

        // Include soft-deleted if requested (trashed view toggle)
        if ($request->boolean('trashed')) {
            $query->onlyTrashed();
        }

        $result = ClassSection::tableQuery(
            request: $request,
            extraFields: [
                'display_name'   => ['header' => 'Section', 'sortable' => true],
                'class_level_id' => ['header' => 'Class Level', 'filterType' => 'dropdown', 'hidden' => true],
                'status'         => ['header' => 'Status', 'filterType' => 'dropdown'],
                'capacity'       => ['header' => 'Capacity', 'filterType' => 'number'],
                'students_count' => ['header' => 'Students', 'sortable' => false, 'filterable' => false],
            ]
        );

        return Inertia::render('Settings/Academic/ClassSections/Index', [
            'initialData'  => ClassSectionResource::collection(
                collect($result['data'])
            ),
            'totalRecords' => $result['totalRecords'],
            'columns'      => $result['columns'],

            // Shared data for the page header and bulk generate modal
            'namingPresets' => ClassSectionNamePresets::toFrontendArray(),

            // Whether the trashed toggle is currently active
            'showTrashed' => $request->boolean('trashed'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Create One
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Create a single class section manually.
     *
     * The class level is resolved from the request body (class_level_id).
     * This allows creation from the standalone index page without being
     * nested under a class level route.
     */
    public function store(StoreClassSectionRequest $request): JsonResponse
    {
        Gate::authorize('create', ClassSection::class);

        // Resolve the class level from the request body
        $classLevel = ClassLevel::findOrFail($request->validated()['class_level_id']
            ?? $request->input('class_level_id'));

        $section = $this->service->createOne($classLevel, $request->validated());

        return response()->json([
            'message' => "Section \"{$section->display_name_computed}\" created successfully.",
            'data'    => new ClassSectionResource($section),
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Update
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Update an existing class section.
     *
     * Supports partial updates (PATCH semantics) — only send changed fields.
     */
    public function update(
        UpdateClassSectionRequest $request,
        ClassSection $classSection
    ): JsonResponse {
        Gate::authorize('update', $classSection);

        $section = $this->service->update($classSection, $request->validated());

        return response()->json([
            'message' => "Section \"{$section->display_name_computed}\" updated successfully.",
            'data'    => new ClassSectionResource($section),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Bulk State-Change Operations
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Soft-delete one or more sections.
     */
    public function destroy(BulkClassSectionRequest $request): JsonResponse
    {
        Gate::authorize('delete', ClassSection::class);

        $count = $this->service->bulkDelete($request->validated()['ids']);

        return response()->json([
            'message' => "{$count} section(s) deleted successfully.",
            'count'   => $count,
        ]);
    }

    /**
     * Restore one or more soft-deleted sections.
     */
    public function restore(BulkClassSectionRequest $request): JsonResponse
    {
        Gate::authorize('restore', ClassSection::class);

        $count = $this->service->bulkRestore($request->validated()['ids']);

        return response()->json([
            'message' => "{$count} section(s) restored successfully.",
            'count'   => $count,
        ]);
    }

    /**
     * Permanently delete one or more trashed sections.
     */
    public function forceDestroy(BulkClassSectionRequest $request): JsonResponse
    {
        Gate::authorize('forceDelete', ClassSection::class);

        $count = $this->service->bulkForceDelete($request->validated()['ids']);

        return response()->json([
            'message' => "{$count} section(s) permanently deleted.",
            'count'   => $count,
        ]);
    }

    /**
     * Activate or deactivate one or more sections.
     */
    public function bulkToggle(BulkClassSectionRequest $request): JsonResponse
    {
        Gate::authorize('toggleStatus', ClassSection::class);

        $validated = $request->validated();
        $count = $this->service->bulkToggleStatus(
            $validated['ids'],
            (bool) $validated['is_active']
        );

        $action = $validated['is_active'] ? 'activated' : 'deactivated';

        return response()->json([
            'message' => "{$count} section(s) {$action} successfully.",
            'count'   => $count,
        ]);
    }

    /**
     * Reorder sections by assigning new sort_order values.
     *
     * Accepts an ordered array of all section IDs reflecting the desired order.
     */
    public function reorder(BulkClassSectionRequest $request): JsonResponse
    {
        Gate::authorize('reorder', ClassSection::class);

        $count = $this->service->reorder($request->validated()['ids']);

        return response()->json([
            'message' => "Section order updated successfully.",
            'updated' => $count,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Bulk Generate
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Generate arm sections across one or multiple class levels.
     *
     * The request resolveArms() method converts (naming_style + arm_count)
     * or custom_arms into the final arm labels array before calling the service.
     *
     * Returns a detailed summary so the frontend modal can display:
     * "9 sections created across 3 class levels (0 skipped)"
     */
    public function bulkGenerate(BulkGenerateClassSectionRequest $request): JsonResponse
    {
        Gate::authorize('bulkGenerate', ClassSection::class);

        $validated = $request->validated();

        // Resolve arm labels from the validated naming strategy
        $arms = $request->resolveArms();

        $result = $this->service->bulkGenerate(
            classLevelIds: $validated['class_level_ids'],
            arms:          $arms,
            defaults:      $validated['defaults'] ?? []
        );

        $message = "{$result['total_created']} section(s) created";
        if ($result['total_skipped'] > 0) {
            $message .= ", {$result['total_skipped']} skipped (already existed)";
        }
        $message .= '.';

        return response()->json([
            'message'       => $message,
            'total_created' => $result['total_created'],
            'total_skipped' => $result['total_skipped'],
            'per_level'     => $result['per_level'],
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Form Teacher Assignment
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Assign or clear the form teacher for a class section.
     *
     * PATCH /class-sections/{section}/teacher
     * Body: { "form_teacher_id": "uuid" }  or  { "form_teacher_id": null }
     */
    public function assignFormTeacher(
        Request $request,
        ClassSection $classSection
    ): JsonResponse {
        Gate::authorize('assignTeacher', $classSection);

        $validated = $request->validate([
            'form_teacher_id' => [
                'nullable',
                'uuid',
                'exists:staff,id',
            ],
        ]);

        $section = $this->service->assignFormTeacher(
            $classSection,
            $validated['form_teacher_id']
        );

        $teacherName = $section->formTeacher?->full_name
            ?? $section->formTeacher?->profile?->full_name
            ?? null;

        $message = $teacherName
            ? "Form teacher set to {$teacherName} for \"{$section->display_name_computed}\"."
            : "Form teacher cleared for \"{$section->display_name_computed}\".";

        return response()->json([
            'message' => $message,
            'data'    => new ClassSectionResource($section),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Subject Teacher Assignments
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Assign a teacher to a subject in a class section.
     *
     * POST /class-sections/{section}/subjects
     */
    public function assignSubject(
        AssignSubjectRequest $request,
        ClassSection $classSection
    ): JsonResponse {
        Gate::authorize('manageSubjects', $classSection);

        $assignment = $this->service->assignSubject($classSection, $request->validated());

        return response()->json([
            'message' => "Subject assignment created successfully.",
            'data'    => [
                'id'         => $assignment->id,
                'teacher_id' => $assignment->teacher_id,
                'subject_id' => $assignment->subject_id,
                'role'       => $assignment->role,
                'role_label' => $assignment->getEffectiveRoleLabel(),
                'teacher'    => [
                    'id'        => $assignment->teacher->id,
                    'full_name' => $assignment->teacher->full_name
                                   ?? $assignment->teacher->profile?->full_name
                                   ?? 'Unknown',
                ],
                'subject' => [
                    'id'   => $assignment->subject->id,
                    'name' => $assignment->subject->name,
                ],
            ],
        ], 201);
    }

    /**
     * Remove a teacher-subject assignment from a section.
     *
     * DELETE /class-sections/{section}/subjects/{assignment}
     */
    public function removeSubject(
        ClassSection $classSection,
        TeacherClassSectionSubject $assignment
    ): JsonResponse {
        Gate::authorize('removeSubjectAssignment', $assignment);

        // Ensure the assignment belongs to this section
        if ($assignment->class_section_id !== $classSection->id) {
            abort(404);
        }

        $this->service->removeSubjectAssignment($assignment);

        return response()->json([
            'message' => 'Subject assignment removed successfully.',
        ]);
    }

    /**
     * Update the role on an existing subject assignment.
     *
     * PATCH /class-sections/{section}/subjects/{assignment}
     */
    public function updateSubjectRole(
        Request $request,
        ClassSection $classSection,
        TeacherClassSectionSubject $assignment
    ): JsonResponse {
        Gate::authorize('manageSubjects', $classSection);

        if ($assignment->class_section_id !== $classSection->id) {
            abort(404);
        }

        $validated = $request->validate([
            'role' => ['nullable', 'string', 'max:50'],
        ]);

        $assignment = $this->service->updateSubjectAssignment(
            $assignment,
            $validated['role']
        );

        return response()->json([
            'message' => 'Role updated successfully.',
            'data'    => [
                'id'         => $assignment->id,
                'role'       => $assignment->role,
                'role_label' => $assignment->getEffectiveRoleLabel(),
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Utility Endpoints
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return naming presets for the BulkGenerateModal.
     *
     * GET /class-sections/presets
     *
     * Returns all preset styles with labels, descriptions, arm lists, and max counts.
     * The frontend uses this to render the naming style selector and live preview.
     */
    public function presets(): JsonResponse
    {
        Gate::authorize('bulkGenerate', ClassSection::class);

        return response()->json([
            'data' => ClassSectionNamePresets::toFrontendArray(),
        ]);
    }

    /**
     * Return a minimal list of active sections for dropdowns and AsyncSelect.
     *
     * GET /class-sections/options
     *     ?class_level_id=uuid    (filter by class level — common use case)
     *     ?school_section_id=uuid (filter by high-level division)
     *     ?include_inactive=true  (include inactive sections)
     *
     * Used by:
     * - Student enrollment form (pick a section)
     * - Timetable builder
     * - Result entry filter
     * - Any AsyncSelect component
     *
     * Returns ClassSectionMinimalResource — lighter than full ClassSectionResource.
     */
    public function options(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ClassSection::class);

        $query = ClassSection::query()
            ->with('classLevel')
            ->withCount('students')
            ->ordered();

        // Filter to active only unless explicitly requesting all
        if (!$request->boolean('include_inactive')) {
            $query->active();
        }

        // Filter by class level if provided
        if ($classLevelId = $request->input('class_level_id')) {
            $query->forClassLevel($classLevelId);
        }

        // Filter by school section (division: JSS, Primary, SSS)
        if ($schoolSectionId = $request->input('school_section_id')) {
            $query->forSchoolSection($schoolSectionId);
        }

        $sections = $query->get();

        return ClassSectionMinimalResource::collection($sections);
    }
}
