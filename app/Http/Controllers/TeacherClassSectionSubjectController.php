<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeacherClassSectionSubjectRequest;
use App\Http\Requests\UpdateTeacherClassSectionSubjectRequest;
use App\Models\Academic\ClassSection;
use App\Models\Academic\Subject;
use App\Models\Academic\TeacherClassSectionSubject;
use App\Models\Employee\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing TeacherClassSectionSubject resources.
 */
class TeacherClassSectionSubjectController extends Controller
{
    /**
     * Display a listing of teacher assignments with dynamic querying.
     *
     * @param Request $request
     * @param ClassSection|null $classSection
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request, ?ClassSection $classSection = null)
    {
        permitted('teacher-assignments.view', $request->wantsJson()); // Check permission

        try {
            // Define extra fields for table query
            $extraFields = [
                [
                    'field' => 'teacher_name',
                    'relation' => 'teacher',
                    'relatedField' => 'full_name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'class_section_name',
                    'relation' => 'classSection',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'subject_name',
                    'relation' => 'subject',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            // Build query
            $query = TeacherClassSectionSubject::with([
                'teacher:id,first_name,last_name',
                'classSection:id,name',
                'subject:id,name,code',
            ])->when($classSection, fn($q) => $q->where('class_section_id', $classSection->id))
              ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query (search, filter, sort, paginate)
            $assignments = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($assignments);
            }

            return Inertia::render('Academic/TeacherAssignments', [
                'classSection' => $classSection ? $classSection->only('id', 'name') : null,
                'assignments' => $assignments,
                'classSections' => ClassSection::select('id', 'name')->get(),
                'teachers' => Staff::select('id', 'first_name', 'last_name')->get(),
                'subjects' => Subject::select('id', 'name', 'code')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch teacher assignments: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch teacher assignments'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch teacher assignments']);
        }
    }

    /**
     * Store a newly created teacher assignment in storage.
     *
     * @param StoreTeacherClassSectionSubjectRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreTeacherClassSectionSubjectRequest $request)
    {
        permitted('teacher-assignments.create', $request->wantsJson()); // Check permission

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id; // Ensure school_id is set
            $assignment = TeacherClassSectionSubject::create($validated);

            // Optionally store role in configs if provided
            if (!empty($validated['role'])) {
                $assignment->configs()->create(['value' => $validated['role']]);
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Teacher assignment created successfully'], 201)
                : redirect()->route('teacher-assignments.index')->with(['success' => 'Teacher assignment created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create teacher assignment: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create teacher assignment'], 500)
                : redirect()->back()->with(['error' => 'Failed to create teacher assignment'])->withInput();
        }
    }

    /**
     * Display the specified teacher assignment.
     *
     * @param Request $request
     * @param TeacherClassSectionSubject $assignment
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, TeacherClassSectionSubject $assignment)
    {
        permitted('teacher-assignments.view', true); // Check permission (JSON response)

        try {
            $assignment->load([
                'teacher:id,first_name,last_name',
                'classSection:id,name',
                'subject:id,name,code',
            ]);
            return response()->json($assignment);
        } catch (\Exception $e) {
            Log::error('Failed to fetch teacher assignment: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch teacher assignment'], 500);
        }
    }

    /**
     * Update the specified teacher assignment in storage.
     *
     * @param UpdateTeacherClassSectionSubjectRequest $request
     * @param TeacherClassSectionSubject $assignment
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateTeacherClassSectionSubjectRequest $request, TeacherClassSectionSubject $assignment)
    {
        permitted('teacher-assignments.update', $request->wantsJson()); // Check permission

        try {
            $validated = $request->validated();
            $assignment->update($validated);

            // Update role in configs if provided
            if (!empty($validated['role'])) {
                $assignment->configs()->create(['value' => $validated['role']]);
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Teacher assignment updated successfully'])
                : redirect()->route('teacher-assignments.index')->with(['success' => 'Teacher assignment updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update teacher assignment: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update teacher assignment'], 500)
                : redirect()->back()->with(['error' => 'Failed to update teacher assignment'])->withInput();
        }
    }

    /**
     * Remove the specified teacher assignment(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        permitted('teacher-assignments.delete', true); // Check permission (JSON response)

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:teacher_class_section_subjects,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $deleted = $forceDelete
                ? TeacherClassSectionSubject::whereIn('id', $ids)->forceDelete()
                : TeacherClassSectionSubject::whereIn('id', $ids)->delete();

            return response()->json([
                'message' => $deleted ? 'Teacher assignment(s) deleted successfully' : 'No assignments were deleted',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete teacher assignments: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete teacher assignment(s)'], 500);
        }
    }

    /**
     * Restore a soft-deleted teacher assignment.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        permitted('teacher-assignments.restore', true); // Check permission (JSON response)

        try {
            $assignment = TeacherClassSectionSubject::withTrashed()->findOrFail($id);
            $assignment->restore();

            return response()->json(['message' => 'Teacher assignment restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore teacher assignment: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore teacher assignment'], 500);
        }
    }
}