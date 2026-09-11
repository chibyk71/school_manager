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
 * AcademicSessionController – Handles CRUD & Activation for Academic Sessions
 *
 * Phase 1: lifecycle authority is the state machine. Activation uses setCurrent /
 * AcademicCalendarService::activateSession — not request-body is_current.
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

            // New sessions default to draft via AcademicSessionState::config()
            $session = AcademicSession::create($validated);

            return redirect()
                ->route('academic-sessions.index')
                ->with('success', "Academic session '{$session->name}' created successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to create academic session', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'user_id' => auth()->id(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create academic session. Please try again.');
        }
    }

    public function update(UpdateAcademicSessionRequest $request, AcademicSession $academicSession)
    {
        Gate::authorize('update', $academicSession);

        try {
            $validated = $request->validated();
            $academicSession->update($validated);

            return redirect()
                ->route('academic-sessions.index')
                ->with('success', "Academic session '{$academicSession->name}' updated successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to update academic session', [
                'error' => $e->getMessage(),
                'session' => $academicSession->id,
                'user_id' => auth()->id(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to update academic session.');
        }
    }

    /**
     * Explicit activation endpoint — lifecycle transition via service, not request flag.
     */
    public function setCurrent(AcademicSession $academicSession)
    {
        Gate::authorize('update', $academicSession);

        try {
            $this->service->activateSession($academicSession);

            return back()->with('success', "Current session switched to '{$academicSession->name}'.");
        } catch (\Exception $e) {
            Log::error('Failed to set current session', [
                'error' => $e->getMessage(),
                'session' => $academicSession->id,
            ]);

            return back()->with('error', 'Failed to switch current session.');
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

            $deleted = AcademicSession::query()->whereIn('id', $validated['ids'])
                ->where('school_id', $schoolId)
                ->where('state', '!=', SessionActive::$name)
                ->delete();

            if ($deleted === 0) {
                return back()->with('error', 'No eligible academic sessions were deleted (active session cannot be deleted).');
            }

            return back()->with('success', "{$deleted} academic session(s) deleted successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to delete academic sessions', [
                'error' => $e->getMessage(),
                'ids' => $validated['ids'] ?? [],
            ]);

            return back()->with('error', 'Failed to delete academic sessions.');
        }
    }

    public function restore(AcademicSession $academicSession)
    {
        Gate::authorize('restore', $academicSession);

        try {
            $newerSession = AcademicSession::where('school_id', GetSchoolModel()->id)
                ->where('start_date', '>', $academicSession->start_date)
                ->exists();

            if ($newerSession) {
                return back()->with('error', 'Cannot restore: newer sessions already exist.');
            }

            $academicSession->restore();

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
            if ($academicSession->state instanceof SessionActive
                || (string) $academicSession->state === SessionActive::$name) {
                return back()->with('error', 'Cannot permanently delete an active session.');
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
