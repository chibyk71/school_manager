<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ResolveTimetableConflictRequest;
use App\Http\Requests\Academic\StoreTimetableSlotRequest;
use App\Http\Requests\Academic\UpdateTimetableSlotRequest;
use App\Http\Resources\Academic\TimetableSlotResource;
use App\Models\Academic\Timetable;
use App\Models\Academic\TimetableConflict;
use App\Models\Academic\TimetableSlot;
use App\Services\Academic\TimetableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * TimetableSlotController — HTTP Layer for Individual Timetable Slot Operations
 *
 * ── Responsibilities ──────────────────────────────────────────────────────────────
 * Handles all HTTP concerns for individual timetable slots — the lesson cells that
 * populate the weekly grid. All write operations route through TimetableService so
 * conflict checking always runs:
 *
 *   index()             List all slots for a timetable (grid data + conflict overlay)
 *   store()             Manually place a slot (teacher, subject, section, day, period)
 *   show()              Fetch a single slot with full relationships
 *   update()            Move or re-assign a slot (drag-drop or edit dialog)
 *   destroy()           Soft-delete a slot
 *   bulkDestroy()       Clear multiple slots (e.g. clear a whole day's arm)
 *   resolveConflict()   Admin resolves a TimetableConflict entry (3 strategies)
 *
 * ── Why Every Write Goes Through TimetableService ────────────────────────────────
 * Direct model creation would bypass:
 *   - Section conflict check (NoSectionConflict rule + service re-validation)
 *   - Teacher conflict check (TimetableSlot::teacherConflictExists)
 *   - Status guard (must be DRAFT to edit)
 *   - Auto-resolution of related conflicts after slot placement
 *
 * The FormRequest validates the incoming data; the service enforces business rules.
 *
 * ── Response Format ───────────────────────────────────────────────────────────────
 * All responses are JSON — this controller is called exclusively via AJAX from the
 * TimetableBuilder.vue Vue component. There are no Inertia redirect responses here
 * because the builder manages its own state reactively (optimistic updates + rollback).
 *
 * ── Route Model Binding ───────────────────────────────────────────────────────────
 * Routes are nested under the timetable:
 *   POST   /timetables/{timetable}/slots
 *   PATCH  /timetables/{timetable}/slots/{slot}
 *   DELETE /timetables/{timetable}/slots/{slot}
 *   POST   /timetables/{timetable}/slots/bulk-destroy
 *   PATCH  /timetables/{timetable}/conflicts/{conflict}/resolve
 *
 * Nesting ensures BelongsToSchool scope is applied on both the timetable and slot,
 * and that slot IDs cannot be used to access a different timetable's slots.
 *
 * ── Multi-Tenant Safety ───────────────────────────────────────────────────────────
 * BelongsToSchool on TimetableSlot + TimetableConflict ensures all queries are
 * scoped to the current school automatically.
 *
 * ── Authorization ─────────────────────────────────────────────────────────────────
 * Uses Gate::authorize() with TimetablePolicy:
 *   'update' (timetable)  → required for store, update, destroy, bulkDestroy
 *   'resolve' (timetable) → required for resolveConflict
 */
class TimetableSlotController extends Controller
{
    public function __construct(
        protected readonly TimetableService $service
    ) {}

    // ──────────────────────────────────────────────────────────────────────────────
    // Read Operations
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * List all slots for a timetable (used by the builder to refresh after generation).
     *
     * Returns slots plus the unresolved conflicts list so the ConflictPanel can
     * render without a separate API call.
     *
     * GET /timetables/{timetable}/slots
     */
    public function index(Request $request, Timetable $timetable): JsonResponse
    {
        Gate::authorize('view', $timetable);

        $slots = $timetable->slots()
            ->with([
                'classSection:id,name,display_name',
                'period:id,name,order,duration_minutes,is_break',
                'assignment.subject:id,name,color',
                'assignment.teacher.profile:id,first_name,last_name,title',
                'timetable:id,status',
            ])
            ->get();

        // Build period timing map (same logic as TimetableController::show)
        $timetable->load('daySchedules.periodSchedule.periods');
        $periodTimingByDay = $this->buildPeriodTimingMap($timetable);

        $slotResources = $slots->map(function ($slot) use ($periodTimingByDay) {
            $resource = new TimetableSlotResource($slot);
            $resource->periodTimes = $periodTimingByDay[$slot->day_of_week] ?? [];
            return $resource;
        });

        // Include unresolved conflicts so the frontend panel updates atomically
        $conflicts = $timetable->unresolvedConflicts()
            ->with([
                'classSection:id,name,display_name',
                'assignment.subject:id,name',
                'assignment.teacher.profile:id,first_name,last_name',
                'period:id,name,order',
            ])
            ->get()
            ->map(fn ($c) => [
                'id'                  => $c->id,
                'type'                => $c->conflict_type,
                'type_label'          => $c->conflict_type_label,
                'severity'            => $c->severity,
                'description'         => $c->description,
                'section_name'        => $c->classSection?->display_name ?? $c->classSection?->name,
                'subject_name'        => $c->assignment?->subject?->name,
                'teacher_name'        => optional($c->assignment?->teacher?->profile)->first_name,
                'period_name'         => $c->period?->name,
                'day_of_week'         => $c->day_of_week,
                'suggestions'         => $c->suggested_alternatives ?? [],
                'can_auto_resolve'    => $c->canAutoResolve(),
            ]);

        return response()->json([
            'slots'     => $slotResources->values(),
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Fetch a single slot with full relationships.
     *
     * Used by the edit dialog to pre-fill the form fields.
     *
     * GET /timetables/{timetable}/slots/{slot}
     */
    public function show(Timetable $timetable, TimetableSlot $slot): JsonResponse
    {
        Gate::authorize('view', $timetable);

        $this->assertSlotBelongsToTimetable($slot, $timetable);

        $slot->load([
            'classSection:id,name,display_name',
            'period:id,name,order,duration_minutes,is_break',
            'assignment.subject:id,name,color,periods_per_week',
            'assignment.teacher.profile:id,first_name,last_name,title',
            'timetable:id,status',
        ]);

        return response()->json(new TimetableSlotResource($slot));
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Write Operations
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Manually place a new lesson slot on the timetable grid.
     *
     * The FormRequest (StoreTimetableSlotRequest) validates:
     *   - Timetable is DRAFT
     *   - Period is not a break
     *   - Period belongs to the day's mapped schedule
     *   - NoSectionConflict (section not double-booked)
     *   - NoTeacherConflict (teacher not double-booked)
     *
     * The service additionally:
     *   - Auto-resolves any NO_AVAILABLE_PERIOD conflicts for the same section+TCSS
     *
     * POST /timetables/{timetable}/slots
     */
    public function store(
        StoreTimetableSlotRequest $request,
        Timetable $timetable
    ): JsonResponse {
        Gate::authorize('update', $timetable);

        try {
            $slot = $this->service->addSlot($timetable, $request->validated());

            return response()->json(new TimetableSlotResource($slot), 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('TimetableSlotController@store failed', [
                'timetable_id' => $timetable->id,
                'data'         => $request->validated(),
                'error'        => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to add slot.'], 500);
        }
    }

    /**
     * Move or re-assign an existing slot (drag-drop or edit dialog).
     *
     * Accepts a partial payload — only the changed fields need to be sent.
     * Common use cases:
     *   - Drag-drop: { day_of_week, class_period_id }
     *   - Subject/teacher swap: { teacher_class_section_subject_id }
     *   - Note update: { notes }
     *
     * After any admin edit, is_manually_placed is set to true (TimetableService).
     *
     * PATCH /timetables/{timetable}/slots/{slot}
     */
    public function update(
        UpdateTimetableSlotRequest $request,
        Timetable $timetable,
        TimetableSlot $slot
    ): JsonResponse {
        Gate::authorize('update', $timetable);

        $this->assertSlotBelongsToTimetable($slot, $timetable);

        try {
            $updated = $this->service->updateSlot($slot, $request->validated());

            return response()->json(new TimetableSlotResource($updated));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('TimetableSlotController@update failed', [
                'timetable_id' => $timetable->id,
                'slot_id'      => $slot->id,
                'data'         => $request->validated(),
                'error'        => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to update slot.'], 500);
        }
    }

    /**
     * Soft-delete a single slot.
     *
     * Safe on both manually placed and auto-generated slots.
     * Timetable must be DRAFT.
     *
     * DELETE /timetables/{timetable}/slots/{slot}
     */
    public function destroy(Timetable $timetable, TimetableSlot $slot): JsonResponse
    {
        Gate::authorize('update', $timetable);

        $this->assertSlotBelongsToTimetable($slot, $timetable);

        try {
            $this->service->removeSlot($slot);

            return response()->json(['message' => 'Slot removed successfully.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('TimetableSlotController@destroy failed', [
                'timetable_id' => $timetable->id,
                'slot_id'      => $slot->id,
                'error'        => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to remove slot.'], 500);
        }
    }

    /**
     * Clear multiple slots in one request.
     *
     * Accepts optional `section_id` to clear only slots for one class section,
     * and optional `day_of_week` to clear only one day's slots.
     * If neither is passed, clears all auto-generated slots for the timetable.
     *
     * The `auto_only` flag (default true) preserves manually placed slots.
     * Pass `auto_only=false` to clear everything (use with caution — shows a
     * confirmation dialog in the frontend before calling this endpoint).
     *
     * POST /timetables/{timetable}/slots/bulk-destroy
     */
    public function bulkDestroy(Request $request, Timetable $timetable): JsonResponse
    {
        Gate::authorize('update', $timetable);

        $validated = $request->validate([
            'slot_ids'   => 'sometimes|array',
            'slot_ids.*' => 'uuid|exists:timetable_slots,id',
            'auto_only'  => 'sometimes|boolean',
            'section_id' => 'sometimes|uuid|exists:class_sections,id',
            'day_of_week' => 'sometimes|integer|between:1,7',
        ]);

        try {
            if (! empty($validated['slot_ids'])) {
                // Delete specific slots by ID
                $slots = TimetableSlot::whereIn('id', $validated['slot_ids'])
                    ->where('timetable_id', $timetable->id)
                    ->get();

                $removed = 0;
                foreach ($slots as $slot) {
                    $this->service->removeSlot($slot);
                    $removed++;
                }
            } else {
                // Delete all auto-generated slots (or all if auto_only=false)
                $autoOnly = $validated['auto_only'] ?? true;

                // Apply section/day filters when bulk-clearing a subset
                $query = TimetableSlot::where('timetable_id', $timetable->id);

                if ($autoOnly) {
                    $query->where('is_manually_placed', false);
                }

                if (isset($validated['section_id'])) {
                    $query->where('class_section_id', $validated['section_id']);
                }

                if (isset($validated['day_of_week'])) {
                    $query->where('day_of_week', $validated['day_of_week']);
                }

                $slots   = $query->get();
                $removed = 0;
                foreach ($slots as $slot) {
                    $slot->delete();
                    $removed++;
                }
            }

            return response()->json([
                'message'       => "{$removed} slot(s) removed successfully.",
                'removed_count' => $removed,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('TimetableSlotController@bulkDestroy failed', [
                'timetable_id' => $timetable->id,
                'payload'      => $validated ?? [],
                'error'        => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to remove slots.'], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Conflict Resolution
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Resolve a timetable conflict using one of three strategies.
     *
     * Strategies (validated by ResolveTimetableConflictRequest):
     *   use_suggestion  — create a slot using a generator-suggested position
     *   manual          — mark resolved; slot already placed by admin
     *   skip            — acknowledge and skip (requires resolution_notes)
     *
     * PATCH /timetables/{timetable}/conflicts/{conflict}/resolve
     */
    public function resolveConflict(
        ResolveTimetableConflictRequest $request,
        Timetable $timetable,
        TimetableConflict $conflict
    ): JsonResponse {
        Gate::authorize('update', $timetable);

        // Ensure the conflict belongs to this timetable (scope safety)
        if ($conflict->timetable_id !== $timetable->id) {
            return response()->json(['error' => 'Conflict not found.'], 404);
        }

        try {
            $resolved = $this->service->resolveConflict($conflict, $request->validated());

            return response()->json([
                'message'  => 'Conflict resolved successfully.',
                'conflict' => [
                    'id'               => $resolved->id,
                    'is_resolved'      => $resolved->isResolved(),
                    'resolved_at'      => $resolved->resolved_at?->toDateTimeString(),
                    'resolution_notes' => $resolved->resolution_notes,
                ],
                // Return the updated conflict count so the frontend panel updates
                'unresolved_conflict_count' => $timetable->unresolvedConflicts()->count(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('TimetableSlotController@resolveConflict failed', [
                'timetable_id' => $timetable->id,
                'conflict_id'  => $conflict->id,
                'data'         => $request->validated(),
                'error'        => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to resolve conflict.'], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Assert that a slot belongs to the given timetable.
     *
     * Route model binding with BelongsToSchool scope prevents cross-school access
     * but does NOT prevent accessing another timetable's slot if the URL is crafted
     * with the wrong {timetable} segment. This check closes that gap.
     *
     * Aborts with 404 rather than 403 to avoid leaking timetable existence.
     *
     * @param  TimetableSlot  $slot
     * @param  Timetable      $timetable
     */
    private function assertSlotBelongsToTimetable(TimetableSlot $slot, Timetable $timetable): void
    {
        if ($slot->timetable_id !== $timetable->id) {
            abort(404);
        }
    }

    /**
     * Build a day_of_week → period_id → {start, end} timing map.
     *
     * Duplicated from TimetableController for slot-only refreshes (calling
     * TimetableController would require coupling the two controllers together).
     * If this grows complex, extract to a shared trait or helper class.
     *
     * @param  Timetable  $timetable  Must have daySchedules.periodSchedule.periods loaded
     * @return array<int, array<int, array{start: string, end: string}>>
     */
    private function buildPeriodTimingMap(Timetable $timetable): array
    {
        $map = [];

        foreach ($timetable->daySchedules as $daySchedule) {
            $schedule = $daySchedule->periodSchedule;
            if (! $schedule) {
                continue;
            }
            $map[$daySchedule->day_of_week] = $schedule->computedPeriodTimes();
        }

        return $map;
    }
}
