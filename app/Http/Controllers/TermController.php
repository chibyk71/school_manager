<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTermRequest;
use App\Http\Requests\UpdateTermRequest;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Services\AcademicSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing Term resources.
 */
class TermController extends Controller
{
    /**
     * Display a listing of terms with dynamic querying.
     *
     * @param Request $request
     * @param AcademicSession|null $academicSession
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request, ?AcademicSession $academicSession = null)
    {
        Gate::authorize('viewAny', Term::class); // Policy-based authorization

        try {
            // If no academic session is provided, use the current session
            $academicSession ??= app(AcademicSessionService::class)->currentSession();

            // Define extra fields for table query
            $extraFields = [
                [
                    'field' => 'academic_session_name',
                    'relation' => 'academicSession',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            // Build query
            $query = Term::with(['academicSession:id,name'])
                ->when($academicSession, fn($q) => $q->forAcademicSession($academicSession->id))
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query (search, filter, sort, paginate)
            $terms = $query->tableQuery($request, $extraFields)->map(function ($term) {
                return [
                    'id' => $term->id,
                    'name' => $term->name,
                    'description' => $term->description,
                    'start_date' => $term->start_date,
                    'start_date_human' => Carbon::parse($term->start_date)->translatedFormat('F j, Y'),
                    'end_date' => $term->end_date,
                    'end_date_human' => Carbon::parse($term->end_date)->translatedFormat('F j, Y'),
                    'status' => $term->status,
                    'color' => $term->color,
                    'academic_session_name' => $term->academicSession?->name,
                ];
            });

            if ($request->wantsJson()) {
                return response()->json($terms);
            }

            return Inertia::render('Academic/Terms', [
                'academicSession' => $academicSession ? $academicSession->only('id', 'name') : null,
                'terms' => $terms,
                'academicSessions' => AcademicSession::select('id', 'name')->get(), // For dropdowns in UI
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch terms: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch terms'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch terms']);
        }
    }

    /**
     * Store a newly created term in storage.
     *
     * @param StoreTermRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreTermRequest $request)
    {
        Gate::authorize('create', Term::class); // Policy-based authorization

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id; // Ensure school_id is set

            // Ensure only one term is active per academic session
            if ($validated['status'] === 'active') {
                Term::where('academic_session_id', $validated['academic_session_id'])
                    ->where('status', 'active')
                    ->update(['status' => 'pending']);
            }

            $term = Term::create($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Term created successfully'], 201)
                : redirect()->route('terms.index')->with(['success' => 'Term created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create term: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create term'], 500)
                : redirect()->back()->with(['error' => 'Failed to create term'])->withInput();
        }
    }

    /**
     * Display the specified term.
     *
     * @param Request $request
     * @param Term $term
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Term $term)
    {
        Gate::authorize('view', $term); // Policy-based authorization

        try {
            $term->load(['academicSession:id,name']);
            return response()->json([
                'id' => $term->id,
                'name' => $term->name,
                'description' => $term->description,
                'start_date' => $term->start_date,
                'start_date_human' => Carbon::parse($term->start_date)->translatedFormat('F j, Y'),
                'end_date' => $term->end_date,
                'end_date_human' => Carbon::parse($term->end_date)->translatedFormat('F j, Y'),
                'status' => $term->status,
                'color' => $term->color,
                'academic_session_name' => $term->academicSession?->name,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch term: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch term'], 500);
        }
    }

    /**
     * Update the specified term in storage.
     *
     * @param UpdateTermRequest $request
     * @param Term $term
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateTermRequest $request, Term $term)
    {
        Gate::authorize('update', $term); // Policy-based authorization

        try {
            $validated = $request->validated();

            // Ensure only one term is active per academic session
            if (isset($validated['status']) && $validated['status'] === 'active') {
                Term::where('academic_session_id', $term->academic_session_id)
                    ->where('status', 'active')
                    ->where('id', '!=', $term->id)
                    ->update(['status' => 'pending']);
            }

            $term->update($validated);

            $message = isset($validated['status']) && $validated['status'] === 'active' && count($validated) === 1
                ? 'Term status updated to active successfully'
                : 'Term updated successfully';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('terms.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to update term: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update term'], 500)
                : redirect()->back()->with(['error' => 'Failed to update term'])->withInput();
        }
    }

    /** Quick “set active” for a term */
    public function setActive(Term $term)
    {
        Gate::authorize('update', $term);
        app(AcademicSessionService::class)->setActiveTerm($term);
        return back()->with('success', 'Active term switched.');
    }

    /**
     * Remove the specified term(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', Term::class); // Policy-based authorization

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:terms,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $deleted = $forceDelete
                ? Term::whereIn('id', $ids)->forceDelete()
                : Term::whereIn('id', $ids)->delete();

            $message = $deleted ? 'Term(s) deleted successfully' : 'No terms were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->back()->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete terms: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete term(s)'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete term(s)']);
        }
    }

    /**
     * Restore a soft-deleted term.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $term = Term::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $term); // Policy-based authorization

        try {
            $term->restore();
            return response()->json(['message' => 'Term restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore term: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore term'], 500);
        }
    }
}
