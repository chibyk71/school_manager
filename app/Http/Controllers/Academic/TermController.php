<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTermRequest;
use App\Http\Requests\UpdateTermRequest;
use App\Http\Resources\Academic\TermResource;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Services\AcademicCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * TermController – Handles CRUD & State Management for Academic Terms
 *
 * Manages all operations related to academic terms within sessions in a multi-tenant environment.
 * All actions are strictly scoped to the current school via GetSchoolModel().
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Full CRUD with policy-based authorization (Gate)
 * • Single active term per session enforcement via AcademicCalendarService
 * • Multi-tenant isolation: every query filters by school_id
 * • Inertia.js rendering for SPA experience with PrimeVue components
 * • Dynamic DataTable support via HasTableQuery trait on model
 * • Bulk soft-delete + individual restore
 * • Quick "set active" action for common admin workflow
 * • Comprehensive error handling + structured logging
 * • Clean separation: business rules delegated to AcademicCalendarService
 * • Responsive, accessible UI-ready (Inertia props are simple & typed)
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Primary controller for term management UI
 * • Works tightly with AcademicSessionController (terms nested under sessions)
 * • Integrates with AcademicCalendarService (activation, closure, date validation)
 * • Supports PrimeVue DataTable (index), Dialog/Forms (create/update)
 * • Prepares for TermClosureController (close/reopen actions)
 * • Aligns with frontend stack: props match Vue 3 + PrimeVue expectations
 *
 * Routes (suggested):
 *   GET    /terms                              → index (all terms or filtered by session)
 *   POST   /terms                              → store
 *   GET    /terms/{term}                       → show
 *   PATCH  /terms/{term}                       → update
 *   DELETE /terms                              → destroy (bulk)
 *   PATCH  /terms/{term}/active                → setActive
 *   POST   /terms/{term}/restore               → restore
 */
class TermController extends Controller
{
    public function __construct(protected AcademicCalendarService $service)
    {
        // Optional: Apply middleware for bulk actions or specific permissions
        // $this->middleware('permission:terms.manage')->except(['index', 'show']);
    }

    /**
     * Display a listing of terms (optionally filtered by academic session).
     */
    public function index(Request $request, ?AcademicSession $academicSession = null)
    {
        Gate::authorize('viewAny', Term::class);

        try {
            // Default to current session if none provided
            $academicSession ??= $this->service->currentSession();

            // Extra fields for DataTable column generation
            $extra = [
                [
                    'field' => 'academic_session_name',
                    'relation' => 'academicSession',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            // Build query with proper scoping
            $query = Term::with(['academicSession:id,name'])
                ->when($academicSession, fn($q) => $q->forSession($academicSession->id))
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Get the full DataTable-ready result from trait
            $result = $query->tableQuery($request, $extra);

            // Transform only the data rows using the resource
            // (keeps computed fields, formatting, etc. in one place)
            $terms = TermResource::collection($result['data']);

            return Inertia::render('Academic/Terms/Index', [
                'academicSession' => $academicSession ? $academicSession->only('id', 'name') : null,
                'terms' => $terms,                    // Resource collection (array of formatted objects)
                'totalRecords' => $result['totalRecords'],
                'currentPage' => $result['currentPage'],
                'lastPage' => $result['lastPage'],
                'perPage' => $result['perPage'],
                'columns' => $result['columns'],        // ← Auto-generated PrimeVue columns!
                'globalFilterables' => $result['globalFilterables'],
                'academicSessions' => AcademicSession::select('id', 'name')->get(),
                'filters' => $request->only(['search', 'sort', 'order', 'perPage', 'with_trashed']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load terms list', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return Inertia::render('Academic/Terms/Index', [
                'terms' => [],
                'error' => 'Unable to load terms at this time.',
            ]);
        }
    }

    /**
     * Store a newly created term.
     */
    public function store(StoreTermRequest $request)
    {
        Gate::authorize('create', Term::class);

        try {
            $validated = $request->validated();

            // Auto-set ordinal_number if not provided
            if (!isset($validated['ordinal_number'])) {
                $validated['ordinal_number'] = Term::where('academic_session_id', $validated['academic_session_id'])
                    ->max('ordinal_number') + 1 ?? 1;
            }

            $term = Term::create($validated);

            // Optional: Auto-activate if status is 'active' (service will enforce single active)
            if ($validated['status'] === 'active') {
                $this->service->activateTerm($term);
            }

            return redirect()
                ->route('terms.index', ['academicSession' => $term->academic_session_id])
                ->with('success', "Term '{$term->name}' created successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to create term', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create term.');
        }
    }

    /**
     * Display the specified term.
     */
    public function show(Term $term)
    {
        Gate::authorize('view', $term);

        $term->load(['academicSession:id,name']);

        return Inertia::render('Academic/Terms/Show', [
            'term' => [
                'id' => $term->id,
                'name' => $term->name,
                'short_name' => $term->short_name,
                'description' => $term->description,
                'start_date' => $term->start_date?->format('Y-m-d'),
                'end_date' => $term->end_date?->format('Y-m-d'),
                'status' => $term->status,
                'is_active' => $term->is_active,
                'is_closed' => $term->is_closed,
                'color' => $term->color,
                'academic_session' => $term->academicSession,
            ],
        ]);
    }

    /**
     * Update the specified term.
     */
    public function update(UpdateTermRequest $request, Term $term)
    {
        Gate::authorize('update', $term);

        try {
            $validated = $request->validated();

            // Handle status change to active (service enforces single active)
            if (isset($validated['status']) && $validated['status'] === 'active') {
                $this->service->activateTerm($term);
                unset($validated['status']); // Avoid double update
            }

            $term->update($validated);

            return redirect()
                ->route('terms.show', $term)
                ->with('success', "Term '{$term->name}' updated successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to update term', [
                'error' => $e->getMessage(),
                'term_id' => $term->id,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to update term.');
        }
    }

    /**
     * Quick action: Set this term as the active one in its session.
     */
    public function setActive(Term $term)
    {
        Gate::authorize('update', $term);

        try {
            $this->service->activateTerm($term);

            return back()->with('success', "Active term switched to '{$term->name}'.");
        } catch (\Exception $e) {
            Log::error('Failed to set active term', [
                'error' => $e->getMessage(),
                'term_id' => $term->id,
            ]);

            return back()->with('error', 'Failed to switch active term.');
        }
    }

    /**
     * Remove one or more terms (bulk soft-delete).
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', Term::class);

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:terms,id',
        ]);

        try {
            $schoolId = GetSchoolModel()->id;

            $deleted = Term::whereIn('id', $validated['ids'])
                ->where('school_id', $schoolId)
                ->where('is_active', false) // Safety: never delete active term
                ->where('is_closed', false) // Optional: prevent deleting closed terms
                ->delete();

            if ($deleted === 0) {
                return back()->with('error', 'No eligible terms were deleted (active/closed terms cannot be deleted).');
            }

            return back()->with('success', "{$deleted} term(s) deleted successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to delete terms', [
                'error' => $e->getMessage(),
                'ids' => $validated['ids'] ?? [],
            ]);

            return back()->with('error', 'Failed to delete terms.');
        }
    }

    /**
     * Restore a soft-deleted term.
     */
    public function restore($id)
    {
        $term = Term::withTrashed()->findOrFail($id);

        Gate::authorize('restore', $term);

        try {
            // Optional safety: prevent restore if session is closed/archived
            if ($term->academicSession->status === 'closed' || $term->academicSession->status === 'archived') {
                return back()->with('error', 'Cannot restore term: parent session is closed or archived.');
            }

            $term->restore();

            return back()->with('success', "Term '{$term->name}' restored successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to restore term', [
                'error' => $e->getMessage(),
                'term_id' => $id,
            ]);

            return back()->with('error', 'Failed to restore term.');
        }
    }
}
