<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreTimetableRequest;
use App\Http\Requests\Academic\UpdateTimetableRequest;
use App\Http\Resources\Academic\TimetableResource;
use App\Http\Resources\Academic\TimetableSlotResource;
use App\Jobs\Academic\GenerateTimetableJob;
use App\Models\Academic\PeriodSchedule;
use App\Models\Academic\SchoolSection;
use App\Models\Academic\Term;
use App\Models\Academic\Timetable;
use App\Services\Academic\TimetableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * TimetableController — HTTP Layer for Timetable Header Operations
 *
 * ── Responsibilities ──────────────────────────────────────────────────────────────
 * Handles all HTTP concerns for the Timetable module header:
 *
 *   index()     List all timetables for the current school (DataTable, Inertia or JSON)
 *   store()     Create a new draft timetable with day-schedule mappings
 *   show()      Load a single timetable with all slots for the builder view
 *   update()    Edit a draft timetable's header/day-schedules
 *   destroy()   Soft-delete a draft timetable
 *
 *   activate()  Switch a draft timetable to ACTIVE (single-active per section+term)
 *   archive()   Move an active or draft timetable to ARCHIVED (read-only history)
 *   generate()  Dispatch the async generation job (returns 202 Accepted immediately)
 *   preview()   Run a dry-run generation and return the result synchronously (no writes)
 *
 * ── What This Controller Does NOT Do ─────────────────────────────────────────────
 * • Does NOT manage individual slots → TimetableSlotController
 * • Does NOT manage period schedules → PeriodScheduleController
 * • Does NOT contain business logic → TimetableService + TimetableGeneratorService
 * • Does NOT run generation synchronously (except preview) → GenerateTimetableJob
 *
 * ── Authorization ─────────────────────────────────────────────────────────────────
 * Uses Gate::authorize() with a TimetablePolicy (assumed to exist). Each action
 * maps to a standard policy method. Activation and archiving require elevated
 * permissions (`activate-timetable`, `archive-timetable`) beyond basic CRUD.
 *
 * ── Dual Response Pattern ─────────────────────────────────────────────────────────
 * All list/show actions return:
 *   - Inertia response for browser requests (full SPA page)
 *   - JSON response for $request->wantsJson() (DataTable AJAX, mobile, API)
 *
 * State-change actions (store/update/destroy/activate/archive) return:
 *   - Inertia redirect with flash for browser requests
 *   - JSON response with the affected resource for API requests
 *
 * ── Multi-Tenant Safety ───────────────────────────────────────────────────────────
 * BelongsToSchool global scope on all models ensures queries are automatically
 * filtered to the current school. Route model binding respects this scope, so a
 * user cannot access another school's timetable by guessing UUIDs.
 *
 * ── Routes (suggested) ────────────────────────────────────────────────────────────
 *   GET    /timetables                        → index
 *   POST   /timetables                        → store
 *   GET    /timetables/{timetable}            → show
 *   PATCH  /timetables/{timetable}            → update
 *   DELETE /timetables/{timetable}            → destroy
 *   PATCH  /timetables/{timetable}/activate   → activate
 *   PATCH  /timetables/{timetable}/archive    → archive
 *   POST   /timetables/{timetable}/generate   → generate
 *   POST   /timetables/{timetable}/preview    → preview
 */
class TimetableController extends Controller
{
    public function __construct(
        protected readonly TimetableService $service
    ) {}

    // ──────────────────────────────────────────────────────────────────────────────
    // CRUD
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * List all timetables for the current school.
     *
     * Supports server-side DataTable via HasTableQuery (search, sort, filter,
     * pagination). Returns Inertia for browser, JSON for AJAX/API.
     *
     * The index page passes dropdown data (sections, terms) so the "Create
     * Timetable" modal is fully functional without a separate API call.
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        Gate::authorize('viewAny', Timetable::class);

        try {
            $query = Timetable::query()
                ->with([
                    'schoolSection:id,name,display_name',
                    'term:id,name',
                    'generatedBy:id,name',
                ])
                ->withCount(['slots', 'unresolvedConflicts'])
                ->when(
                    $request->filled('status'),
                    fn ($q) => $q->where('status', $request->input('status'))
                )
                ->when(
                    $request->filled('section_id'),
                    fn ($q) => $q->where('school_section_id', $request->input('section_id'))
                )
                ->when(
                    $request->filled('term_id'),
                    fn ($q) => $q->where('term_id', $request->input('term_id'))
                );

            $tableData = $query->tableQuery($request);

            $timetables = TimetableResource::collection($tableData['data']);

            if ($request->wantsJson()) {
                return response()->json([
                    'data'         => $timetables,
                    'totalRecords' => $tableData['totalRecords'],
                    'columns'      => $tableData['columns'],
                ]);
            }

            return Inertia::render('Academic/Timetable/Index', [
                'timetables'   => $timetables,
                'totalRecords' => $tableData['totalRecords'],
                'columns'      => $tableData['columns'],
                // Dropdown data for the Create modal
                'sections'     => SchoolSection::select('id', 'name', 'display_name')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(),
                'terms'        => Term::select('id', 'name', 'academic_session_id')
                    ->with('academicSession:id,name')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(),
                'periodSchedules' => PeriodSchedule::select('id', 'name', 'color')
                    ->where('is_active', true)
                    ->ordered()
                    ->get(),
                'filters'      => $request->only(['status', 'section_id', 'term_id', 'search']),
            ]);
        } catch (\Throwable $e) {
            Log::error('TimetableController@index failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to load timetables.'], 500);
            }

            return Inertia::render('Academic/Timetable/Index', [
                'timetables' => [],
                'error'      => 'Failed to load timetables. Please try again.',
            ]);
        }
    }

    /**
     * Create a new draft timetable.
     *
     * Validated by StoreTimetableRequest (blocks duplicate active timetable,
     * validates day_schedules nested array, scopes exists rules to school).
     * Always creates with status=draft regardless of input.
     */
    public function store(StoreTimetableRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Timetable::class);

        try {
            $timetable = $this->service->createTimetable($request->validated());

            if ($request->wantsJson()) {
                return response()->json(
                    new TimetableResource($timetable),
                    201
                );
            }

            return redirect()
                ->route('timetable.show', $timetable->id)
                ->with('success', "Timetable \"{$timetable->title}\" created successfully.");
        } catch (\Throwable $e) {
            Log::error('TimetableController@store failed', [
                'data'  => $request->validated(),
                'error' => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to create timetable.'], 500);
            }

            return redirect()->back()->withInput()
                ->with('error', 'Failed to create timetable. Please try again.');
        }
    }

    /**
     * Load a single timetable with all slots for the builder/view page.
     *
     * For the BUILDER (draft): returns all slots with full relationship data
     * so the grid renders without additional API calls.
     *
     * For the VIEW (active/archived): returns the same shape but the frontend
     * renders in read-only mode.
     *
     * Period timing is computed here (not stored) and merged into the slot
     * resources via the periodTimes array on TimetableSlotResource.
     */
    public function show(Request $request, Timetable $timetable): InertiaResponse|JsonResponse
    {
        Gate::authorize('view', $timetable);

        $timetable->load([
            'schoolSection:id,name,display_name',
            'term:id,name',
            'generatedBy:id,name',
            'daySchedules.periodSchedule.periods',
            'unresolvedConflicts',
        ]);

        $timetable->loadCount(['slots', 'unresolvedConflicts']);

        // Load slots with all relationships needed by the grid
        $slots = $timetable->slots()
            ->with([
                'classSection:id,name,display_name',
                'period:id,name,order,duration_minutes,is_break',
                'assignment.subject:id,name,color',
                'assignment.teacher.profile:id,first_name,last_name,title',
                'timetable:id,status',
            ])
            ->get();

        // Build period timing map (day → periodId → {start, end})
        // Grouped by day_of_week since different days may use different schedules
        $periodTimingByDay = $this->buildPeriodTimingMap($timetable);

        // Attach timing to each slot resource
        $slotResources = $slots->map(function ($slot) use ($periodTimingByDay) {
            $resource = new TimetableSlotResource($slot);
            $resource->periodTimes = $periodTimingByDay[$slot->day_of_week] ?? [];
            return $resource;
        });

        $timetableResource = new TimetableResource($timetable);

        if ($request->wantsJson()) {
            return response()->json([
                'timetable' => $timetableResource,
                'slots'     => $slotResources->values(),
            ]);
        }

        // Determine which Inertia page to render based on status
        $page = $timetable->isDraft() ? 'Academic/Timetable/Builder' : 'Academic/Timetable/View';

        return Inertia::render($page, [
            'timetable'       => $timetableResource,
            'slots'           => $slotResources->values(),
            // For the builder sidebar: all sections and their TCSS assignments
            'classSections'   => $this->loadClassSectionsForBuilder($timetable),
            // Period schedule reference for the builder grid header
            'periodSchedules' => $this->loadPeriodSchedulesForTimetable($timetable),
        ]);
    }

    /**
     * Update a draft timetable's header and/or day-schedule mappings.
     *
     * Active and archived timetables are rejected by TimetableService::updateTimetable().
     */
    public function update(
        UpdateTimetableRequest $request,
        Timetable $timetable
    ): RedirectResponse|JsonResponse {
        Gate::authorize('update', $timetable);

        try {
            $updated = $this->service->updateTimetable($timetable, $request->validated());

            if ($request->wantsJson()) {
                return response()->json(new TimetableResource($updated));
            }

            return redirect()
                ->route('timetable.show', $timetable->id)
                ->with('success', 'Timetable updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $e->errors()], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            Log::error('TimetableController@update failed', [
                'timetable_id' => $timetable->id,
                'error'        => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to update timetable.'], 500);
            }

            return redirect()->back()->withInput()
                ->with('error', 'Failed to update timetable.');
        }
    }

    /**
     * Soft-delete a draft timetable.
     *
     * Only draft timetables can be deleted. Active and archived ones are blocked
     * by TimetableService::deleteTimetable().
     */
    public function destroy(Request $request, Timetable $timetable): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $timetable);

        try {
            $this->service->deleteTimetable($timetable);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Timetable deleted successfully.']);
            }

            return redirect()
                ->route('timetable.index')
                ->with('success', "Timetable \"{$timetable->title}\" deleted.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $e->errors()], 422);
            }
            return redirect()->back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            Log::error('TimetableController@destroy failed', [
                'timetable_id' => $timetable->id,
                'error'        => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to delete timetable.'], 500);
            }

            return redirect()->back()->with('error', 'Failed to delete timetable.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // State Transitions
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Activate a draft timetable.
     *
     * Blocked if:
     *   - Timetable has unresolved conflicts (TimetableService throws ValidationException)
     *   - Timetable is not in DRAFT status
     *
     * On success, all other active timetables for the same section+term are
     * archived atomically (handled inside Timetable::activate() transaction).
     *
     * PATCH /timetables/{timetable}/activate
     */
    public function activate(Request $request, Timetable $timetable): RedirectResponse|JsonResponse
    {
        Gate::authorize('activate', $timetable);

        try {
            $activated = $this->service->activateTimetable($timetable);

            if ($request->wantsJson()) {
                return response()->json([
                    'message'   => "Timetable \"{$timetable->title}\" is now active.",
                    'timetable' => new TimetableResource($activated),
                ]);
            }

            return redirect()
                ->route('timetable.show', $timetable->id)
                ->with('success', "Timetable \"{$timetable->title}\" activated successfully.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $e->errors()], 422);
            }
            return redirect()->back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            Log::error('TimetableController@activate failed', [
                'timetable_id' => $timetable->id,
                'error'        => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to activate timetable.'], 500);
            }

            return redirect()->back()->with('error', 'Failed to activate timetable.');
        }
    }

    /**
     * Archive a timetable (moves to read-only historical status).
     *
     * Both DRAFT and ACTIVE timetables can be archived.
     * Archived timetables cannot be re-activated — a new draft must be created.
     *
     * PATCH /timetables/{timetable}/archive
     */
    public function archive(Request $request, Timetable $timetable): RedirectResponse|JsonResponse
    {
        Gate::authorize('archive', $timetable);

        try {
            $archived = $this->service->archiveTimetable($timetable);

            if ($request->wantsJson()) {
                return response()->json([
                    'message'   => "Timetable \"{$timetable->title}\" archived.",
                    'timetable' => new TimetableResource($archived),
                ]);
            }

            return redirect()
                ->route('timetable.index')
                ->with('success', "Timetable \"{$timetable->title}\" has been archived.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $e->errors()], 422);
            }
            return redirect()->back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            Log::error('TimetableController@archive failed', [
                'timetable_id' => $timetable->id,
                'error'        => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to archive timetable.'], 500);
            }

            return redirect()->back()->with('error', 'Failed to archive timetable.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Generation
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Dispatch the timetable generation job asynchronously.
     *
     * Returns 202 Accepted immediately — the actual work happens on the queue.
     * The frontend polls for status or relies on the in-app notification to know
     * when generation completes.
     *
     * Preconditions (checked here, not in service):
     *   - Timetable must be DRAFT
     *   - At least one day-schedule mapping must exist
     *
     * POST /timetables/{timetable}/generate
     */
    public function generate(Request $request, Timetable $timetable): RedirectResponse|JsonResponse
    {
        Gate::authorize('generate', $timetable);

        if (! $timetable->isDraft()) {
            $error = 'Only draft timetables can be generated.';

            if ($request->wantsJson()) {
                return response()->json(['error' => $error], 422);
            }
            return redirect()->back()->with('error', $error);
        }

        if ($timetable->daySchedules()->doesntExist()) {
            $error = 'Please map at least one day to a period schedule before generating.';

            if ($request->wantsJson()) {
                return response()->json(['error' => $error], 422);
            }
            return redirect()->back()->with('error', $error);
        }

        // Dispatch to the timetable queue — returns immediately
        GenerateTimetableJob::dispatch($timetable, auth()->id(), preview: false);

        Log::info('Timetable generation job dispatched', [
            'timetable_id' => $timetable->id,
            'dispatched_by' => auth()->id(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message'      => 'Generation started. You will be notified when it completes.',
                'timetable_id' => $timetable->id,
                'status'       => 'queued',
            ], 202);
        }

        return redirect()
            ->route('timetable.show', $timetable->id)
            ->with('info', 'Generation has been queued. You will receive a notification when it completes.');
    }

    /**
     * Run a dry-run generation synchronously and return the result.
     *
     * Does NOT write to the database. Useful for showing the admin what would
     * happen (how many conflicts, coverage percentage) before committing to a
     * full generation run.
     *
     * Because this runs synchronously, it's subject to the HTTP timeout.
     * For very large timetables, consider dispatching a preview job instead.
     *
     * POST /timetables/{timetable}/preview
     */
    public function preview(
        Request $request,
        Timetable $timetable,
        \App\Services\Academic\TimetableGeneratorService $generator
    ): JsonResponse {
        Gate::authorize('generate', $timetable);

        if (! $timetable->isDraft()) {
            return response()->json(['error' => 'Only draft timetables can be previewed.'], 422);
        }

        try {
            $result = $generator->previewGenerate($timetable);

            return response()->json([
                'message' => 'Preview complete. No changes have been saved.',
                'result'  => $result,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('TimetableController@preview failed', [
                'timetable_id' => $timetable->id,
                'error'        => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Preview failed. Please try again.'], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Build a day_of_week → period_id → {start, end} timing map.
     *
     * Each day may use a different PeriodSchedule (e.g. short Friday).
     * We compute the clock times from each schedule's start_time + cumulative
     * durations and return them keyed by day so TimetableSlotResource can
     * attach the right timing to each slot without querying again.
     *
     * @param  Timetable  $timetable  Must have daySchedules.periodSchedule.periods loaded
     * @return array<int, array<int, array{start: string, end: string}>>
     */
    private function buildPeriodTimingMap(Timetable $timetable): array
    {
        $map = [];

        foreach ($timetable->daySchedules as $daySchedule) {
            $day      = $daySchedule->day_of_week;
            $schedule = $daySchedule->periodSchedule;

            if (! $schedule) {
                continue;
            }

            $timings       = $schedule->computedPeriodTimes(); // period_id → {start, end}
            $map[$day]     = $timings;
        }

        return $map;
    }

    /**
     * Load class sections (arms) that belong to the timetable's school section,
     * along with their TCSS assignments (subjects + teachers) for the builder sidebar.
     *
     * The builder sidebar shows a list of unplaced assignments so the admin can
     * drag them onto the grid.
     *
     * @param  Timetable  $timetable
     * @return \Illuminate\Support\Collection
     */
    private function loadClassSectionsForBuilder(Timetable $timetable): \Illuminate\Support\Collection
    {
        return $timetable->schoolSection
            ->classLevels()
            ->with([
                'classSections' => fn ($q) => $q->with([
                    'teacherSubjectAssignments.subject:id,name,color,periods_per_week',
                    'teacherSubjectAssignments.teacher.profile:id,first_name,last_name',
                ]),
            ])
            ->get()
            ->flatMap(fn ($level) => $level->classSections)
            ->map(fn ($section) => [
                'id'           => $section->id,
                'name'         => $section->name,
                'display_name' => $section->display_name ?? $section->name,
                'assignments'  => $section->teacherSubjectAssignments->map(fn ($tcss) => [
                    'id'             => $tcss->id,
                    'subject_id'     => $tcss->subject_id,
                    'subject_name'   => $tcss->subject?->name,
                    'subject_color'  => $tcss->subject?->color,
                    'periods_per_week' => $tcss->subject?->periods_per_week,
                    'teacher_name'   => $this->resolveTeacherShortName($tcss->teacher),
                ]),
            ]);
    }

    /**
     * Load period schedule details for each working day — used to render the
     * correct period-column headers in the timetable grid.
     *
     * @param  Timetable  $timetable
     * @return array
     */
    private function loadPeriodSchedulesForTimetable(Timetable $timetable): array
    {
        return $timetable->daySchedules
            ->load('periodSchedule.periods')
            ->sortBy('day_of_week')
            ->map(fn ($ds) => [
                'day_of_week'   => $ds->day_of_week,
                'schedule_id'   => $ds->period_schedule_id,
                'schedule_name' => $ds->periodSchedule?->name,
                'periods'       => $ds->periodSchedule?->periods
                    ->sortBy('order')
                    ->map(fn ($p) => [
                        'id'               => $p->id,
                        'name'             => $p->name,
                        'order'            => $p->order,
                        'duration_minutes' => $p->duration_minutes,
                        'is_break'         => $p->is_break,
                    ])
                    ->values(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Resolve a compact teacher name from the teacher model.
     * Replicates the logic in TimetableSlotResource for consistency.
     *
     * @param  mixed  $teacher
     * @return string|null
     */
    private function resolveTeacherShortName(mixed $teacher): ?string
    {
        if (! $teacher) {
            return 'TBA';
        }

        $profile = $teacher->profile ?? $teacher->primaryProfile ?? null;

        if ($profile) {
            $title    = $profile->title ? "{$profile->title} " : '';
            $first    = $profile->first_name ?? '';
            $lastInit = $profile->last_name
                ? strtoupper(substr($profile->last_name, 0, 1)) . '.'
                : '';

            return trim("{$title}{$first} {$lastInit}") ?: null;
        }

        return $teacher->full_name ?? $teacher->name ?? null;
    }
}
