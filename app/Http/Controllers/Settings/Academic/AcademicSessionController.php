<?php

namespace App\Http\Controllers\Settings\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAcademicSessionRequest;
use App\Http\Requests\UpdateAcademicSessionRequest;
use App\Http\Resources\Academic\AcademicSessionResource;
use App\Models\Academic\AcademicSession;
use App\Services\AcademicCalendarService;
use App\States\Academic\AcademicSession\Active as SessionActive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * AcademicSessionController – Handles CRUD for Academic Sessions
 *
 * Phase 2: lifecycle authority is AcademicSessionLifecycleService.
 * setCurrent has been removed; use plan/activate/pause/resume/close/reopen.
 * Date mutations go through updateDates(); deletion is conservative.
 */
class AcademicSessionController extends Controller
{
    public function __construct(protected AcademicCalendarService $service)
    {
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', AcademicSession::class);

        try {
            $extra = [
                [
                    'field' => 'term_count',
                    'relation' => 'terms',
                    'aggregate' => 'count',
                    'sortable' => true,
                    'header' => 'Terms',
                ],
            ];

            $query = AcademicSession::query()
                ->withCount('terms')
                ->when($request->boolean('with_trashed'), fn ($q) => $q->withTrashed());

            $result = $query->tableQuery($request, $extra);
            $sessions = AcademicSessionResource::collection($result['data']);

            return Inertia::render('Settings/Academic/AcademicSession', [
                'sessions' => $sessions,
                'totalRecords' => $result['totalRecords'],
                'currentPage' => $result['currentPage'],
                'lastPage' => $result['lastPage'],
                'perPage' => $result['perPage'],
                'columns' => $result['columns'],
                'globalFilterables' => $result['globalFilterables'],
                'filters' => $request->only(['search', 'sort', 'order', 'perPage', 'with_trashed']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load academic sessions list', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id() ?? 'system',
            ]);

            return Inertia::render('Settings/Academic/AcademicSession', [
                'sessions' => [],
                'error' => 'Unable to load academic sessions at this time.',
            ]);
        }
    }

    public function store(StoreAcademicSessionRequest $request)
    {
        Gate::authorize('create', AcademicSession::class);

        try {
            $validated = $request->validated();
            $validated['school_id'] = GetSchoolModel()->id;
            $session = AcademicSession::create($validated);

            return back()->with('success', "Academic session '{$session->name}' created successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to create academic session', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create academic session.');
        }
    }

    public function show(AcademicSession $academicSession)
    {
        Gate::authorize('view', $academicSession);

        $academicSession->load('terms');

        return Inertia::render('Settings/Academic/AcademicSessionShow', [
            'session' => new AcademicSessionResource($academicSession),
        ]);
    }

    public function update(UpdateAcademicSessionRequest $request, AcademicSession $academicSession)
    {
        Gate::authorize('update', $academicSession);

        try {
            $validated = $request->validated();
            $lifecycle = app(\App\Services\Academic\AcademicSessionLifecycleService::class);

            // Name may update directly; date mutations go through the domain service
            // so operational-data boundary and overlap rules cannot be bypassed.
            if (array_key_exists('name', $validated) && $validated['name'] !== $academicSession->name) {
                $academicSession->forceFill(['name' => $validated['name']])->save();
            }

            $hasDateChange = array_key_exists('start_date', $validated)
                || array_key_exists('end_date', $validated);

            if ($hasDateChange) {
                $lifecycle->updateDates(
                    $academicSession,
                    $validated['start_date'] ?? null,
                    $validated['end_date'] ?? null,
                );
            }

            return back()->with('success', "Academic session '{$academicSession->name}' updated successfully.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update academic session', [
                'error' => $e->getMessage(),
                'session_id' => $academicSession->id,
                'user_id' => auth()->id(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to update academic session.');
        }
    }

    public function destroy(Request $request)
    {
        Gate::authorize('delete', AcademicSession::class);

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:academic_sessions,id',
        ]);

        try {
            $schoolId = GetSchoolModel()->id;

            // Phase 2: conservative deletion.
            // Protect ACTIVE/PAUSED (current) and CLOSED (historical) until Phase 4
            // usage registry can distinguish sessions with operational data.
            // Only DRAFT / PLANNED without terms are eligible for soft-delete here.
            $deleted = AcademicSession::query()->whereIn('id', $validated['ids'])
                ->where('school_id', $schoolId)
                ->whereIn('state', [
                    \App\States\Academic\AcademicSession\Draft::$name,
                    \App\States\Academic\AcademicSession\Planned::$name,
                ])
                ->whereDoesntHave('terms')
                ->delete();

            if ($deleted === 0) {
                return back()->with('error', 'No eligible academic sessions were deleted. Current, closed, or term-linked sessions cannot be deleted.');
            }

            return back()->with('success', "{$deleted} academic session(s) deleted successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to delete academic sessions', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete academic sessions.');
        }
    }

    public function restore(AcademicSession $academicSession)
    {
        Gate::authorize('restore', $academicSession);

        try {
            // Phase 2: restore only un-soft-deletes. It does not reopen/activate.
            $academicSession->restore();

            app(\App\Services\Academic\AcademicSessionLifecycleService::class)
                ->invalidateCaches($academicSession->school_id);

            return back()->with('success', "Academic session '{$academicSession->name}' restored successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to restore academic session', [
                'error' => $e->getMessage(),
                'session_id' => $academicSession->id,
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to restore academic session.');
        }
    }

    public function forceDelete(AcademicSession $academicSession)
    {
        Gate::authorize('forceDelete', $academicSession);

        try {
            // Phase 2: only DRAFT/PLANNED without terms may be force-deleted.
            // CLOSED historical sessions are protected until Phase 4 usage registry.
            if ($academicSession->isCurrentOperational()) {
                return back()->with('error', 'Cannot permanently delete a current operational session (ACTIVE or PAUSED).');
            }

            if ($academicSession->state instanceof \App\States\Academic\AcademicSession\Closed) {
                return back()->with('error', 'Cannot permanently delete a closed historical session until operational-data registry is available.');
            }

            if ($academicSession->terms()->exists()) {
                return back()->with('error', 'Cannot permanently delete a session with associated terms.');
            }

            $academicSession->forceDelete();

            return back()->with('success', "Academic session '{$academicSession->name}' permanently deleted.");
        } catch (\Exception $e) {
            Log::error('Failed to force delete academic session', [
                'error' => $e->getMessage(),
                'session_id' => $academicSession->id,
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to permanently delete academic session.');
        }
    }
}
