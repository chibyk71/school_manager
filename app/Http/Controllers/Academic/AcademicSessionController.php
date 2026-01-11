<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAcademicSessionRequest;
use App\Http\Requests\UpdateAcademicSessionRequest;
use App\Http\Resources\Academic\AcademicSessionResource;
use App\Models\Academic\AcademicSession;
use App\Services\AcademicCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * AcademicSessionController – Handles CRUD & Activation for Academic Sessions
 *
 * Manages all operations related to academic sessions (school years) in a multi-tenant environment.
 * All actions are strictly scoped to the current school via GetSchoolModel().
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Full CRUD with proper authorization via Laravel Policies (Gate)
 * • Single active/current session enforcement using AcademicCalendarService
 * • Multi-tenant isolation: every operation checks school ownership
 * • Inertia.js + Vue 3 rendering for SPA feel with PrimeVue components
 * • Bulk delete support (with safety checks)
 * • Quick toggle for setting current session (common admin action)
 * • Comprehensive error handling + logging for production debugging
 * • Clean separation: controller delegates business rules to AcademicCalendarService
 * • Responsive, accessible UI-ready (Inertia props match frontend expectations)
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Primary entry point for session management UI (AcademicSessions/Index.vue, Show.vue)
 * • Works tightly with AcademicCalendarService (activation, current session, date rules)
 * • Integrates with Term management (terms shown in session show view)
 * • Prepares for future events/notifications (e.g. SessionActivatedNotification)
 * • Aligns with frontend stack: Inertia props are simple arrays/objects
 * • Supports PrimeVue DataTable (via HasTableQuery trait on model)
 *
 * Routes (suggested):
 *   GET    /academic-sessions              → index
 *   POST   /academic-sessions              → store
 *   GET    /academic-sessions/{session}    → show (optional)
 *   PATCH  /academic-sessions/{session}    → update
 *   DELETE /academic-sessions              → destroy (bulk)
 *   PATCH  /academic-sessions/{session}/current → setCurrent
 */
class AcademicSessionController extends Controller
{
    public function __construct(protected AcademicCalendarService $service)
    {
        // Optional: Apply middleware for specific actions
        // $this->middleware('permission:academic-sessions.manage')->except(['index']);
    }

    /**
     * Display a listing of academic sessions for the current school.
     *
     * Utilizes the HasTableQuery trait for server-side DataTable functionality
     * (search, sort, filter, pagination) and returns the full result set to enable
     * dynamic PrimeVue DataTable columns in the frontend.
     *
     * Key Improvements:
     * ────────────────────────────────────────────────────────────────
     * • Passes the complete tableQuery result (data + columns + pagination metadata)
     *   → Enables fully dynamic DataTable with auto-generated columns (no hard-coded Vue columns)
     * • Removes manual can_* permission flags → Frontend uses usePermissions() composable
     * • Leverages AcademicSessionResource for consistent, secure data transformation
     * • Preserves all filtering/sorting state for seamless Inertia navigation
     * • Production-ready: error handling, logging, multi-tenant safety
     *
     * Frontend Impact (Academic/AcademicSessions/Index.vue):
     * ────────────────────────────────────────────────────────────────
     * • PrimeVue DataTable can use :columns="columns" for dynamic rendering
     * • Lazy loading, pagination, global search all work out-of-the-box
     * • Action buttons gated via usePermissions() composable
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', AcademicSession::class);

        try {
            // Extra column: term count (aggregated via relation)
            $extra = [
                [
                    'field' => 'term_count',
                    'relation' => 'terms',
                    'aggregate' => 'count',
                    'sortable' => true,
                    'header' => 'Terms',
                ],
            ];

            // Build base query with eager loading for efficiency
            $query = AcademicSession::query()
                ->withCount('terms') // Ensures term_count is available
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Get full DataTable result from trait (includes data, columns, pagination, etc.)
            $result = $query->tableQuery($request, $extra);

            // Transform data rows using the dedicated resource (consistent formatting, computed fields)
            $sessions = AcademicSessionResource::collection($result['data']);

            return Inertia::render('Settings/Academic/AcademicSession', [
                'sessions' => $sessions,                    // Resource collection
                'totalRecords' => $result['totalRecords'],
                'currentPage' => $result['currentPage'],
                'lastPage' => $result['lastPage'],
                'perPage' => $result['perPage'],
                'columns' => $result['columns'],           // Auto-generated PrimeVue columns
                'globalFilterables' => $result['globalFilterables'],
                'filters' => $request->only(['search', 'sort', 'order', 'perPage', 'with_trashed']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load academic sessions list', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id() ?? 'system',
            ]);

            // Change to error page
            return Inertia::render('Settings/Academic/AcademicSession', [
                'sessions' => [],
                'error' => 'Unable to load academic sessions at this time.',
            ]);
        }
    }

    /**
     * Store a newly created academic session.
     */
    public function store(StoreAcademicSessionRequest $request)
    {
        Gate::authorize('create', AcademicSession::class);

        try {
            $validated = $request->validated();

            $session = AcademicSession::create($validated);

            // If marked as current, use service to enforce single current session
            if ($request->boolean('is_current')) {
                $this->service->activateSession($session);
            }

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

    /**
     * Update an existing academic session.
     */
    public function update(UpdateAcademicSessionRequest $request, AcademicSession $academicSession)
    {
        Gate::authorize('update', $academicSession);

        try {
            $validated = $request->validated();

            // Special handling for making this the current session
            if ($request->boolean('is_current')) {
                $this->service->activateSession($academicSession);
                unset($validated['is_current']); // Avoid double-flagging
            }

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
     * Quick action: Set this session as the current/active one.
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

    /**
     * Remove one or more academic sessions (bulk soft-delete).
     */
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
                ->where('is_current', false) // Safety: never delete current session
                ->delete();

            if ($deleted === 0) {
                return back()->with('error', 'No eligible academic sessions were deleted (current session cannot be deleted).');
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

    /**
     * Restore a soft-deleted academic session.
     */
    public function restore(AcademicSession $academicSession)
    {
        Gate::authorize('restore', $academicSession);

        try {
            // Safety: cannot restore if a newer session is current/active
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

    /**
     * Permanently delete a soft-deleted academic session (force delete).
     *
     * Extremely restricted action — only for cleanup of erroneous/historical data.
     */
    public function forceDelete(AcademicSession $academicSession)
    {
        Gate::authorize('forceDelete', $academicSession);

        try {
            // Extra safety: only allow if session is not current and has no terms/results/etc.
            if ($academicSession->is_current) {
                return back()->with('error', 'Cannot permanently delete the current active session.');
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
