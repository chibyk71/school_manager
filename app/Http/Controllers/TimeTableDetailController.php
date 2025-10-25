<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimeTableDetailRequest;
use App\Http\Requests\UpdateTimeTableDetailRequest;
use App\Models\Academic\ClassPeriod;
use App\Models\Academic\TeacherClassSectionSubject;
use App\Models\Academic\TimeTable;
use App\Models\Academic\TimeTableDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing TimeTableDetail resources.
 */
class TimeTableDetailController extends Controller
{
    /**
     * Display a listing of timetable details with dynamic querying.
     *
     * @param Request $request
     * @param TimeTable|null $timetable
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request, ?TimeTable $timetable = null)
    {
        Gate::authorize('viewAny', TimeTableDetail::class); // Policy-based authorization

        try {
            // Define extra fields for table query
            $extraFields = [
                [
                    'field' => 'timetable_title',
                    'relation' => 'timetable',
                    'relatedField' => 'title',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'class_period_name',
                    'relation' => 'classPeriod',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'teacher_name',
                    'relation' => 'teacherClassSectionSubject.teacher',
                    'relatedField' => 'full_name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'subject_name',
                    'relation' => 'teacherClassSectionSubject.subject',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'class_section_name',
                    'relation' => 'teacherClassSectionSubject.classSection',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            // Build query
            $query = TimeTableDetail::with([
                'timetable:id,title',
                'classPeriod:id,name',
                'teacherClassSectionSubject.teacher:id,first_name,last_name',
                'teacherClassSectionSubject.subject:id,name',
                'teacherClassSectionSubject.classSection:id,name',
            ])->when($timetable, fn($q) => $q->forTimetable($timetable->id))
              ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query (search, filter, sort, paginate)
            $details = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($details);
            }

            return Inertia::render('Academic/TimetableDetails', [
                'timetable' => $timetable ? $timetable->only('id', 'title') : null,
                'details' => $details,
                'timetables' => TimeTable::select('id', 'title')->get(),
                'classPeriods' => ClassPeriod::select('id', 'name')->get(),
                'teacherAssignments' => TeacherClassSectionSubject::with([
                    'teacher:id,first_name,last_name',
                    'subject:id,name',
                    'classSection:id,name',
                ])->select('id', 'teacher_id', 'class_section_id', 'subject_id')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch timetable details: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch timetable details'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch timetable details']);
        }
    }

    /**
     * Store a newly created timetable detail in storage.
     *
     * @param StoreTimeTableDetailRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreTimeTableDetailRequest $request)
    {
        Gate::authorize('create', TimeTableDetail::class); // Policy-based authorization

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id; // Ensure school_id is set

            $detail = TimeTableDetail::create($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Timetable detail created successfully'], 201)
                : redirect()->route('timetable-details.index')->with(['success' => 'Timetable detail created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create timetable detail: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create timetable detail'], 500)
                : redirect()->back()->with(['error' => 'Failed to create timetable detail'])->withInput();
        }
    }

    /**
     * Display the specified timetable detail.
     *
     * @param Request $request
     * @param TimeTableDetail $timeTableDetail
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, TimeTableDetail $timeTableDetail)
    {
        Gate::authorize('view', $timeTableDetail); // Policy-based authorization

        try {
            $timeTableDetail->load([
                'timetable:id,title',
                'classPeriod:id,name',
                'teacherClassSectionSubject.teacher:id,first_name,last_name',
                'teacherClassSectionSubject.subject:id,name',
                'teacherClassSectionSubject.classSection:id,name',
            ]);
            return response()->json($timeTableDetail);
        } catch (\Exception $e) {
            Log::error('Failed to fetch timetable detail: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch timetable detail'], 500);
        }
    }

    /**
     * Update the specified timetable detail in storage.
     *
     * @param UpdateTimeTableDetailRequest $request
     * @param TimeTableDetail $timeTableDetail
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateTimeTableDetailRequest $request, TimeTableDetail $timeTableDetail)
    {
        Gate::authorize('update', $timeTableDetail); // Policy-based authorization

        try {
            $validated = $request->validated();
            $timeTableDetail->update($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Timetable detail updated successfully'])
                : redirect()->route('timetable-details.index')->with(['success' => 'Timetable detail updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update timetable detail: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update timetable detail'], 500)
                : redirect()->back()->with(['error' => 'Failed to update timetable detail'])->withInput();
        }
    }

    /**
     * Remove the specified timetable detail(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', TimeTableDetail::class); // Policy-based authorization

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:time_table_details,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $deleted = $forceDelete
                ? TimeTableDetail::whereIn('id', $ids)->forceDelete()
                : TimeTableDetail::whereIn('id', $ids)->delete();

            $message = $deleted ? 'Timetable detail(s) deleted successfully' : 'No timetable details were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->back()->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete timetable details: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete timetable detail(s)'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete timetable detail(s)']);
        }
    }

    /**
     * Restore a soft-deleted timetable detail.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $timeTableDetail = TimeTableDetail::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $timeTableDetail); // Policy-based authorization

        try {
            $timeTableDetail->restore();
            return response()->json(['message' => 'Timetable detail restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore timetable detail: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore timetable detail'], 500);
        }
    }
}