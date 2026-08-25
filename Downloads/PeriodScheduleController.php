<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StorePeriodScheduleRequest;
use App\Http\Requests\Academic\UpdatePeriodScheduleRequest;
use App\Models\Academic\ClassPeriod;
use App\Models\Academic\PeriodSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * PeriodScheduleController — HTTP Layer for Bell Schedule Administration
 *
 * ── What This Is ──────────────────────────────────────────────────────────────────
 * Period schedules are the school's "bell schedule" templates — named day patterns
 * (e.g. "Regular Day", "Short Friday", "Exam Day") that define what periods exist
 * and how long each lasts. They are a prerequisite for creating timetables:
 * admins must build at least one schedule before mapping it to timetable days.
 *
 * ── Responsibilities ──────────────────────────────────────────────────────────────
 * Handles all HTTP concerns for period schedules AND their nested ClassPeriod rows.
 * Periods are managed inline (not as a separate nested resource) because they are
 * always created/updated in context of their parent schedule:
 *
 *   index()           List all schedules with period counts (settings list view)
 *   store()           Create a schedule with its periods in one transaction
 *   show()            Fetch a schedule with full period details + computed timings
 *   update()          Edit a schedule header and/or sync its periods
 *   destroy()         Soft-delete a schedule (blocks if in-use by timetables)
 *
 *   activate()        Set a schedule as active (available for timetable mapping)
 *   deactivate()      Mark a schedule inactive (hides from timetable dropdowns)
 *   reorder()         Update the display sort_order for the schedule list
 *
 * ── Business Rules (enforced here, not in a separate service) ────────────────────
 * Period schedule logic is simple enough to not warrant a dedicated service class:
 *   - Cannot delete a schedule that is mapped to any TimetableDaySchedule row
 *   - Cannot delete a schedule if any of its periods are referenced by slots
 *   - Period `order` values must be unique within a schedule (validated in FormRequest)
 *   - Period names must be unique within a schedule (validated in FormRequest)
 *
 * ── Period Sync Strategy ──────────────────────────────────────────────────────────
 * On update, periods are synced using a "delete-then-reinsert" pattern:
 *   1. Soft-delete any ClassPeriod rows not present in the incoming payload
 *   2. Update existing rows (matched by ID if provided in payload)
 *   3. Create new rows for payload entries without an ID
 *
 * This is safe because ClassPeriod has SoftDeletes — historical slot references
 * via class_period_id are not broken if a period is "removed" from a schedule.
 *
 * ── Inertia + JSON Dual Mode ──────────────────────────────────────────────────────
 * index() and show() return Inertia for browser, JSON for API clients.
 * Write actions return Inertia redirects for browser, JSON for API clients.
 *
 * ── Settings-Level Resource ───────────────────────────────────────────────────────
 * This controller lives under the Settings menu (Academic → Period Schedules), not
 * the timetable builder. It is a school-wide configuration resource. Only users
 * with admin-level permission can access it.
 *
 * ── Routes (suggested) ────────────────────────────────────────────────────────────
 *   GET    /settings/period-schedules                         → index
 *   POST   /settings/period-schedules                         → store
 *   GET    /settings/period-schedules/{schedule}              → show
 *   PATCH  /settings/period-schedules/{schedule}              → update
 *   DELETE /settings/period-schedules/{schedule}              → destroy
 *   PATCH  /settings/period-schedules/{schedule}/activate     → activate
 *   PATCH  /settings/period-schedules/{schedule}/deactivate   → deactivate
 *   PATCH  /settings/period-schedules/reorder                 → reorder
 */
class PeriodScheduleController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────────
    // CRUD
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * List all period schedules for the current school.
     *
     * Returns each schedule with its period count and in-use flag so the list
     * view can show which schedules are actively referenced by timetables.
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        Gate::authorize('viewAny', PeriodSchedule::class);

        $schedules = PeriodSchedule::query()
            ->withCount(['periods', 'timetableDays'])
            ->ordered()
            ->get()
            ->map(fn ($s) => [
                'id'                 => $s->id,
                'name'               => $s->name,
                'description'        => $s->description,
                'color'              => $s->color,
                'school_start_time'  => $s->school_start_time,
                'is_active'          => $s->is_active,
                'sort_order'         => $s->sort_order,
                'periods_count'      => $s->periods_count,
                'lesson_period_count' => $s->lessonPeriodCount(),
                'total_duration_min' => $s->totalDurationMinutes(),
                'in_use'             => $s->isInUse(),
                'timetable_days_count' => $s->timetable_days_count,
            ]);

        if ($request->wantsJson()) {
            return response()->json(['data' => $schedules]);
        }

        return Inertia::render('Settings/Academic/PeriodSchedules/Index', [
            'schedules' => $schedules,
            'crumbs'    => [
                ['label' => 'Settings'],
                ['label' => 'Academic'],
                ['label' => 'Period Schedules'],
            ],
        ]);
    }

    /**
     * Create a new period schedule with its periods.
     *
     * The FormRequest (StorePeriodScheduleRequest) validates:
     *   - Schedule header fields (name, color, school_start_time)
     *   - Nested periods[] array (unique order, unique name, at least one non-break)
     *
     * All periods are created in the same transaction as the schedule header.
     */
    public function store(StorePeriodScheduleRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', PeriodSchedule::class);

        try {
            $schedule = DB::transaction(function () use ($request): PeriodSchedule {
                $data = $request->validated();

                $schedule = PeriodSchedule::create([
                    'name'              => $data['name'],
                    'description'       => $data['description'] ?? null,
                    'school_start_time' => $data['school_start_time'] ?? null,
                    'color'             => $data['color'] ?? null,
                    'is_active'         => $data['is_active'] ?? true,
                    'sort_order'        => $data['sort_order'] ?? null,
                ]);

                $this->syncPeriods($schedule, $data['periods'] ?? []);

                Log::info('PeriodSchedule created', [
                    'schedule_id'   => $schedule->id,
                    'period_count'  => count($data['periods'] ?? []),
                ]);

                return $schedule->load('periods');
            });

            if ($request->wantsJson()) {
                return response()->json(
                    $this->formatScheduleDetail($schedule),
                    201
                );
            }

            return redirect()
                ->route('period-schedules.show', $schedule->id)
                ->with('success', "Period schedule \"{$schedule->name}\" created successfully.");
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $e->errors()], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            Log::error('PeriodScheduleController@store failed', [
                'data'  => $request->validated(),
                'error' => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to create period schedule.'], 500);
            }

            return redirect()->back()->withInput()
                ->with('error', 'Failed to create period schedule. Please try again.');
        }
    }

    /**
     * Show a single period schedule with all its periods and computed timings.
     *
     * The computed timings (start/end time per period) are calculated from
     * school_start_time + cumulative durations — never stored in DB.
     * We include them here so the frontend form shows "09:00 – 09:45" next to
     * each period row.
     */
    public function show(Request $request, PeriodSchedule $periodSchedule): InertiaResponse|JsonResponse
    {
        Gate::authorize('view', $periodSchedule);

        $periodSchedule->load(['periods' => fn ($q) => $q->ordered()]);

        $detail = $this->formatScheduleDetail($periodSchedule);

        if ($request->wantsJson()) {
            return response()->json($detail);
        }

        return Inertia::render('Settings/Academic/PeriodSchedules/Show', [
            'schedule' => $detail,
            'crumbs'   => [
                ['label' => 'Settings'],
                ['label' => 'Academic'],
                ['label' => 'Period Schedules', 'url' => route('period-schedules.index')],
                ['label' => $periodSchedule->name],
            ],
        ]);
    }

    /**
     * Update a period schedule header and sync its periods.
     *
     * Period sync strategy:
     *   - Periods with an `id` field: update in place (preserve DB id, avoid FK breaks)
     *   - Periods without an `id`: create new
     *   - Periods whose `id` was omitted from the payload: soft-delete
     *
     * Blocked if schedule is in use and the change would remove a period that is
     * referenced by an existing TimetableSlot. The in-use guard is enforced via
     * ClassPeriod::isInUse() before deletion.
     */
    public function update(
        UpdatePeriodScheduleRequest $request,
        PeriodSchedule $periodSchedule
    ): RedirectResponse|JsonResponse {
        Gate::authorize('update', $periodSchedule);

        try {
            $updated = DB::transaction(function () use ($request, $periodSchedule): PeriodSchedule {
                $data = $request->validated();

                // Update header fields (only those present in payload)
                $periodSchedule->update(array_filter([
                    'name'              => $data['name']              ?? null,
                    'description'       => $data['description']       ?? null,
                    'school_start_time' => $data['school_start_time'] ?? null,
                    'color'             => $data['color']             ?? null,
                    'is_active'         => $data['is_active']         ?? null,
                    'sort_order'        => $data['sort_order']        ?? null,
                ], fn ($v) => $v !== null));

                // Handle explicit null for school_start_time (clearing it)
                if (array_key_exists('school_start_time', $data) && $data['school_start_time'] === null) {
                    $periodSchedule->update(['school_start_time' => null]);
                }

                // Sync periods if included in the payload
                if (array_key_exists('periods', $data)) {
                    $this->syncPeriods($periodSchedule, $data['periods']);
                }

                Log::info('PeriodSchedule updated', ['schedule_id' => $periodSchedule->id]);

                return $periodSchedule->fresh(['periods' => fn ($q) => $q->ordered()]);
            });

            if ($request->wantsJson()) {
                return response()->json($this->formatScheduleDetail($updated));
            }

            return redirect()
                ->route('period-schedules.show', $periodSchedule->id)
                ->with('success', "Period schedule \"{$periodSchedule->name}\" updated.");
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $e->errors()], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            Log::error('PeriodScheduleController@update failed', [
                'schedule_id' => $periodSchedule->id,
                'error'       => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to update period schedule.'], 500);
            }

            return redirect()->back()->withInput()
                ->with('error', 'Failed to update period schedule.');
        }
    }

    /**
     * Soft-delete a period schedule.
     *
     * Blocked if the schedule is referenced by any TimetableDaySchedule row —
     * deleting it would break existing timetables. The admin must first unmap it
     * from all timetables before deletion is allowed.
     */
    public function destroy(Request $request, PeriodSchedule $periodSchedule): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $periodSchedule);

        // Business rule: cannot delete if in use by any timetable day mapping
        if ($periodSchedule->isInUse()) {
            $error = "Cannot delete \"{$periodSchedule->name}\" — it is currently " .
                     "mapped to {$periodSchedule->timetableDays()->count()} timetable day(s). " .
                     "Remove those mappings first.";

            if ($request->wantsJson()) {
                return response()->json(['error' => $error], 422);
            }
            return redirect()->back()->with('error', $error);
        }

        try {
            // Soft-delete the schedule — periods are soft-deleted by cascade observer
            // (or individually if the model doesn't have a cascading observer)
            DB::transaction(function () use ($periodSchedule) {
                // Soft-delete periods first to maintain referential history
                $periodSchedule->periods()->each(fn ($p) => $p->delete());
                $periodSchedule->delete();
            });

            Log::info('PeriodSchedule deleted', ['schedule_id' => $periodSchedule->id]);

            if ($request->wantsJson()) {
                return response()->json(['message' => "Period schedule deleted."]);
            }

            return redirect()
                ->route('period-schedules.index')
                ->with('success', "Period schedule \"{$periodSchedule->name}\" deleted.");
        } catch (\Throwable $e) {
            Log::error('PeriodScheduleController@destroy failed', [
                'schedule_id' => $periodSchedule->id,
                'error'       => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to delete period schedule.'], 500);
            }

            return redirect()->back()->with('error', 'Failed to delete period schedule.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // State Actions
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Activate a period schedule (make it available in timetable day-mapping dropdowns).
     *
     * PATCH /settings/period-schedules/{schedule}/activate
     */
    public function activate(Request $request, PeriodSchedule $periodSchedule): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $periodSchedule);

        $periodSchedule->activate();

        if ($request->wantsJson()) {
            return response()->json([
                'message'   => "\"{$periodSchedule->name}\" is now active.",
                'is_active' => true,
            ]);
        }

        return redirect()->back()
            ->with('success', "\"{$periodSchedule->name}\" activated.");
    }

    /**
     * Deactivate a period schedule (hide from timetable dropdowns without deleting).
     *
     * Blocked if the schedule is currently mapped to any timetable days — deactivating
     * it would prevent editing those timetables in the future.
     *
     * PATCH /settings/period-schedules/{schedule}/deactivate
     */
    public function deactivate(Request $request, PeriodSchedule $periodSchedule): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $periodSchedule);

        if ($periodSchedule->isInUse()) {
            $error = "Cannot deactivate \"{$periodSchedule->name}\" while it is " .
                     "mapped to timetables. Remap those timetable days first.";

            if ($request->wantsJson()) {
                return response()->json(['error' => $error], 422);
            }
            return redirect()->back()->with('error', $error);
        }

        $periodSchedule->deactivate();

        if ($request->wantsJson()) {
            return response()->json([
                'message'   => "\"{$periodSchedule->name}\" deactivated.",
                'is_active' => false,
            ]);
        }

        return redirect()->back()
            ->with('success', "\"{$periodSchedule->name}\" deactivated.");
    }

    /**
     * Update the sort_order of multiple period schedules at once.
     *
     * Accepts an ordered array of IDs. Sort order is assigned as
     * (position + 1) * 10 (10-gap convention matching the rest of the codebase).
     *
     * PATCH /settings/period-schedules/reorder
     */
    public function reorder(Request $request): JsonResponse
    {
        Gate::authorize('update', PeriodSchedule::class);

        $validated = $request->validate([
            'ordered_ids'   => 'required|array|min:1',
            'ordered_ids.*' => 'integer|exists:period_schedules,id',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                foreach ($validated['ordered_ids'] as $position => $id) {
                    PeriodSchedule::where('id', $id)
                        ->update(['sort_order' => ($position + 1) * 10]);
                }
            });

            return response()->json([
                'message' => 'Sort order updated.',
                'updated' => count($validated['ordered_ids']),
            ]);
        } catch (\Throwable $e) {
            Log::error('PeriodScheduleController@reorder failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to reorder schedules.'], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Sync the ClassPeriod rows for a schedule.
     *
     * Strategy:
     *   1. Collect IDs of periods that should survive (those with an 'id' key in payload)
     *   2. Soft-delete any existing periods NOT in that survival list
     *      - Skip if the period is in-use (has TimetableSlot references) → throw
     *   3. Update surviving periods (matched by ID)
     *   4. Create new periods (payload entries without an 'id')
     *
     * @param  PeriodSchedule  $schedule
     * @param  array           $periodsPayload  Validated periods array from request
     * @throws ValidationException  If a to-be-deleted period is still in use
     */
    private function syncPeriods(PeriodSchedule $schedule, array $periodsPayload): void
    {
        // IDs from payload that should be kept (those with an explicit 'id')
        $keepIds = collect($periodsPayload)
            ->filter(fn ($p) => isset($p['id']))
            ->pluck('id')
            ->toArray();

        // Find existing periods not in the keep list
        $toDelete = ClassPeriod::where('period_schedule_id', $schedule->id)
            ->when(! empty($keepIds), fn ($q) => $q->whereNotIn('id', $keepIds))
            ->get();

        // Guard: cannot remove periods that are referenced by slots
        $inUseNames = $toDelete->filter(fn ($p) => $p->isInUse())->pluck('name');
        if ($inUseNames->isNotEmpty()) {
            throw ValidationException::withMessages([
                'periods' => "Cannot remove period(s) [{$inUseNames->join(', ')}] — " .
                             "they are referenced by existing timetable slots. " .
                             "Remove those slots first.",
            ]);
        }

        // Soft-delete removable periods
        foreach ($toDelete as $period) {
            $period->delete();
        }

        // Process each period in the payload
        foreach ($periodsPayload as $periodData) {
            if (isset($periodData['id'])) {
                // Update existing period
                ClassPeriod::where('id', $periodData['id'])
                    ->where('period_schedule_id', $schedule->id) // scope safety
                    ->update([
                        'name'              => $periodData['name'],
                        'order'             => $periodData['order'],
                        'duration_minutes'  => $periodData['duration_minutes'],
                        'is_break'          => $periodData['is_break'] ?? false,
                    ]);
            } else {
                // Create new period
                ClassPeriod::create([
                    'period_schedule_id' => $schedule->id,
                    'name'               => $periodData['name'],
                    'order'              => $periodData['order'],
                    'duration_minutes'   => $periodData['duration_minutes'],
                    'is_break'           => $periodData['is_break'] ?? false,
                ]);
            }
        }
    }

    /**
     * Format a PeriodSchedule model into the full detail array for API/Inertia.
     *
     * Includes computed period timings (start/end clock times) derived from
     * school_start_time + cumulative durations. These are never stored in the DB;
     * they are always computed on-the-fly.
     *
     * @param  PeriodSchedule  $schedule  Must have 'periods' relation loaded + ordered
     * @return array
     */
    private function formatScheduleDetail(PeriodSchedule $schedule): array
    {
        // Compute clock times for each period (null if school_start_time not set)
        $timings = $schedule->school_start_time
            ? $schedule->computedPeriodTimes()
            : [];

        return [
            'id'                  => $schedule->id,
            'name'                => $schedule->name,
            'description'         => $schedule->description,
            'color'               => $schedule->color,
            'school_start_time'   => $schedule->school_start_time,
            'is_active'           => $schedule->is_active,
            'sort_order'          => $schedule->sort_order,
            'in_use'              => $schedule->isInUse(),
            'lesson_period_count' => $schedule->lessonPeriodCount(),
            'total_duration_min'  => $schedule->totalDurationMinutes(),
            // Full period list with computed timings merged in
            'periods' => ($schedule->relationLoaded('periods') ? $schedule->periods : $schedule->periods()->ordered()->get())
                ->map(fn ($p) => [
                    'id'               => $p->id,
                    'name'             => $p->name,
                    'order'            => $p->order,
                    'duration_minutes' => $p->duration_minutes,
                    'is_break'         => (bool) $p->is_break,
                    'start_time'       => $timings[$p->id]['start'] ?? null,
                    'end_time'         => $timings[$p->id]['end']   ?? null,
                ])
                ->values(),
            'created_at' => $schedule->created_at?->toDateTimeString(),
            'updated_at' => $schedule->updated_at?->toDateTimeString(),
        ];
    }
}
