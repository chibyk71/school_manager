<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimeTableRequest;
use App\Http\Requests\UpdateTimeTableRequest;
use App\Jobs\GenerateTimeTableEntries;
use App\Models\Academic\SchoolSection;
use App\Models\Academic\Term;
use App\Models\Academic\TimeTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing TimeTable resources.
 */
class TimeTableController extends Controller
{
    /**
     * Display a listing of timetables with dynamic querying.
     *
     * @param Request $request
     * @param Term|null $term
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request, ?Term $term = null)
    {
        Gate::authorize('viewAny', TimeTable::class); // Policy-based authorization

        try {
            // Define extra fields for table query
            $extraFields = [
                [
                    'field' => 'term_name',
                    'relation' => 'term',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'school_section_names',
                    'relation' => 'schoolSections',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => false,
                    'filterType' => 'text',
                ],
            ];

            // Build query
            $query = TimeTable::with(['term:id,name', 'schoolSections:id,name'])
                ->when($term, fn($q) => $q->forTerm($term->id))
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query (search, filter, sort, paginate)
            $timetables = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($timetables);
            }

            return Inertia::render('Academic/Timetables', [
                'term' => $term ? $term->only('id', 'name') : null,
                'timetables' => $timetables,
                'terms' => Term::select('id', 'name')->get(),
                'schoolSections' => SchoolSection::select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch timetables: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch timetables'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch timetables']);
        }
    }

    /**
     * Store a newly created timetable in storage.
     *
     * @param StoreTimeTableRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreTimeTableRequest $request)
    {
        Gate::authorize('create', TimeTable::class); // Policy-based authorization

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id; // Ensure school_id is set

            // Ensure only one timetable is active per term
            if ($validated['status'] === 'active') {
                TimeTable::where('term_id', $validated['term_id'])
                    ->where('status', 'active')
                    ->update(['status' => 'inactive']);
            }

            $timetable = TimeTable::create($validated);

            // Attach school sections if provided
            if (!empty($validated['school_sections'])) {
                $timetable->attachSections($validated['school_sections']);
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Timetable created successfully'], 201)
                : redirect()->route('timetables.index')->with(['success' => 'Timetable created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create timetable: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create timetable'], 500)
                : redirect()->back()->with(['error' => 'Failed to create timetable'])->withInput();
        }
    }

    /**
     * Display the specified timetable.
     *
     * @param Request $request
     * @param TimeTable $timetable
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, TimeTable $timetable)
    {
        Gate::authorize('view', $timetable); // Policy-based authorization

        try {
            $timetable->load(['term:id,name', 'schoolSections:id,name', 'slots']);
            return response()->json($timetable);
        } catch (\Exception $e) {
            Log::error('Failed to fetch timetable: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch timetable'], 500);
        }
    }

    /**
     * Update the specified timetable in storage.
     *
     * @param UpdateTimeTableRequest $request
     * @param TimeTable $timetable
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateTimeTableRequest $request, TimeTable $timetable)
    {
        Gate::authorize('update', $timetable); // Policy-based authorization

        try {
            $validated = $request->validated();

            // Ensure only one timetable is active per term
            if (isset($validated['status']) && $validated['status'] === 'active') {
                TimeTable::where('term_id', $timetable->term_id)
                    ->where('status', 'active')
                    ->where('id', '!=', $timetable->id)
                    ->update(['status' => 'inactive']);
            }

            $timetable->update($validated);

            // Sync school sections if provided
            if (isset($validated['school_sections'])) {
                $timetable->syncSections($validated['school_sections']);
            }

            $message = isset($validated['status']) && $validated['status'] === 'active' && count($validated) === 1
                ? 'Timetable status updated to active successfully'
                : 'Timetable updated successfully';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('timetables.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to update timetable: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update timetable'], 500)
                : redirect()->back()->with(['error' => 'Failed to update timetable'])->withInput();
        }
    }

    /**
     * Remove the specified timetable(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', TimeTable::class); // Policy-based authorization

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:time_tables,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $deleted = $forceDelete
                ? TimeTable::whereIn('id', $ids)->forceDelete()
                : TimeTable::whereIn('id', $ids)->delete();

            $message = $deleted ? 'Timetable(s) deleted successfully' : 'No timetables were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->back()->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete timetables: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete timetable(s)'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete timetable(s)']);
        }
    }

    /**
     * Restore a soft-deleted timetable.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $timetable = TimeTable::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $timetable); // Policy-based authorization

        try {
            $timetable->restore();
            return response()->json(['message' => 'Timetable restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore timetable: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore timetable'], 500);
        }
    }

    public function generate(Request $request, TimeTable $timetable)
    {
        Gate::authorize('update', $timetable);
        GenerateTimeTableEntries::dispatch($timetable, $request->boolean('dry_run'));
        return response()->json(['message' => 'Timetable generation queued']);
    }
}