<?php

namespace App\Http\Controllers\Settings\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreTermRequest;
use App\Http\Requests\Academic\UpdateTermRequest;
use App\Http\Resources\Academic\TermResource;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Services\AcademicCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * TermController – Handles CRUD & State Management for Academic Terms
 *
 * Manages all operations related to academic terms within sessions in a multi-tenant environment.
 * All actions are strictly scoped to the current school via GetSchoolModel().
 *
 * Features:
 * - Full CRUD with policy-based authorization (Gate)
 * - Domain mutations exclusively via TermLifecycleService
 * - Multi-tenant isolation: every query filters by school_id
 * - Inertia.js rendering for SPA experience with PrimeVue components
 * - Bulk soft-delete + individual restore
 * - Quick set-active action
 */
class TermController extends Controller
{
    public function __construct(protected AcademicCalendarService $service)
    {
    }

    /**
     * Display a listing of terms (optionally filtered by academic session).
     */
    public function index(Request $request, ?AcademicSession $academicSession = null)
    {
        Gate::authorize('viewAny', Term::class);

        try {
            $academicSession ??= $this->service->currentSession();

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

            $school = GetSchoolModel();
            if (! $school) {
                abort(403, 'No active school context.');
            }

            // Tenant boundary: session must belong to current school when supplied
            if ($academicSession && (string) $academicSession->school_id !== (string) $school->id) {
                abort(404);
            }

            // Terms always scoped through session → school
            $query = Term::with(['academicSession:id,name,school_id'])
                ->whereHas('academicSession', fn ($q) => $q->where('school_id', $school->id))
                ->when($academicSession, fn ($q) => $q->forSession($academicSession->id))
                ->when($request->boolean('with_trashed'), fn ($q) => $q->withTrashed());

            $result = $query->tableQuery($request, $extra);
            $terms = TermResource::collection($result['data']);

            return Inertia::render('Academic/Terms/Index', [
                'academicSession' => $academicSession ? $academicSession->only('id', 'name') : null,
                'terms' => $terms,
                'totalRecords' => $result['totalRecords'],
                'currentPage' => $result['currentPage'],
                'lastPage' => $result['lastPage'],
                'perPage' => $result['perPage'],
                'columns' => $result['columns'],
                'globalFilterables' => $result['globalFilterables'],
                'academicSessions' => AcademicSession::query()
                    ->where('school_id', $school->id)
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get(),
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

            $session = AcademicSession::query()->findOrFail($validated['academic_session_id']);
            $lifecycle = app(\App\Services\Academic\TermLifecycleService::class);

            $term = $lifecycle->create($session, [
                'name' => $validated['name'],
                'short_name' => $validated['short_name'] ?? null,
                'description' => $validated['description'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'color' => $validated['color'] ?? null,
                'options' => $validated['options'] ?? null,
            ]);

            return redirect()
                ->route('terms.index', ['academicSession' => $term->academic_session_id])
                ->with('success', "Term '{$term->name}' created successfully.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
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

        $state = $term->state;
        $stateValue = is_object($state) && method_exists($state, 'getValue')
            ? $state->getValue()
            : (string) $state;

        return Inertia::render('Academic/Terms/Show', [
            'term' => [
                'id' => $term->id,
                'name' => $term->name,
                'short_name' => $term->short_name,
                'description' => $term->description,
                'start_date' => $term->start_date?->format('Y-m-d'),
                'end_date' => $term->end_date?->format('Y-m-d'),
                'state' => $stateValue,
                'state_label' => $term->state_label,
                'ordinal_number' => $term->ordinal_number,
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
            $lifecycle = app(\App\Services\Academic\TermLifecycleService::class);

            $attrs = [];
            foreach (['name', 'short_name', 'description', 'start_date', 'end_date', 'color', 'options'] as $key) {
                if (array_key_exists($key, $validated)) {
                    $attrs[$key] = $validated[$key];
                }
            }

            $term = $lifecycle->update($term, $attrs);

            return redirect()
                ->route('terms.show', $term)
                ->with('success', "Term '{$term->name}' updated successfully.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
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
        Gate::authorize('activate', $term);

        try {
            $lifecycle = app(\App\Services\Academic\TermLifecycleService::class);
            $lifecycle->activate($term);

            return back()->with('success', "Active term switched to '{$term->name}'.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
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
            $lifecycle = app(\App\Services\Academic\TermLifecycleService::class);

            $terms = Term::whereIn('id', $validated['ids'])
                ->whereHas('academicSession', fn ($q) => $q->where('school_id', $schoolId))
                ->get();

            $deleted = 0;
            foreach ($terms as $term) {
                try {
                    Gate::authorize('delete', $term);
                    $lifecycle->delete($term);
                    $deleted++;
                } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                    // skip unauthorized terms
                } catch (\Illuminate\Validation\ValidationException $e) {
                    // skip blocked terms
                }
            }

            if ($deleted === 0) {
                return back()->with('error', 'No eligible terms were deleted (operational or protected terms cannot be deleted).');
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
            $lifecycle = app(\App\Services\Academic\TermLifecycleService::class);
            $term = $lifecycle->restore($term);

            return back()->with('success', "Term '{$term->name}' restored successfully.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to restore term', [
                'error' => $e->getMessage(),
                'term_id' => $id,
            ]);

            return back()->with('error', 'Failed to restore term.');
        }
    }
}
