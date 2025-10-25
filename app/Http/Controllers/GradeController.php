<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGradeRequest;
use App\Http\Requests\UpdateGradeRequest;
use App\Models\Academic\Grade;
use App\Models\SchoolSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing Grade resources.
 */
class GradeController extends Controller
{
    /**
     * Display a listing of grades with dynamic querying.
     *
     * @param Request $request
     * @param SchoolSection|null $schoolSection
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request, ?SchoolSection $schoolSection = null)
    {
        permitted('grades.view', $request->wantsJson()); // Check permission

        try {
            // Define extra fields for table query (e.g., related school section name)
            $extraFields = [
                [
                    'field' => 'school_section_name',
                    'relation' => 'schoolSection',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            // Build query
            $query = Grade::with(['schoolSection:id,name'])
                ->when($schoolSection, fn($q) => $q->forSchoolSection($schoolSection->id));

            // Apply dynamic table query (search, filter, sort, paginate)
            $grades = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($grades);
            }

            return Inertia::render('Academic/Exam/Grades', [
                'schoolSection' => $schoolSection ? $schoolSection->only('id', 'name') : null,
                'grades' => $grades,
                'schoolSections' => SchoolSection::select('id', 'name')->get(), // For dropdowns in UI
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch grades: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch grades'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch grades']);
        }
    }

    /**
     * Store a newly created grade in storage.
     *
     * @param StoreGradeRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreGradeRequest $request)
    {
        permitted('grades.create', $request->wantsJson()); // Check permission

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id; // Ensure school_id is set
            Grade::create($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Grade created successfully'], 201)
                : redirect()->back()->with(['success' => 'Grade created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create grade: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create grade'], 500)
                : redirect()->back()->with(['error' => 'Failed to create grade']);
        }
    }

    /**
     * Display the specified grade.
     *
     * @param Request $request
     * @param Grade $grade
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Grade $grade)
    {
        permitted('grades.view', true); // Check permission (JSON response)

        try {
            $grade->load(['schoolSection:id,name']);
            return response()->json($grade);
        } catch (\Exception $e) {
            Log::error('Failed to fetch grade: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch grade'], 500);
        }
    }

    /**
     * Update the specified grade in storage.
     *
     * @param UpdateGradeRequest $request
     * @param Grade $grade
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateGradeRequest $request, Grade $grade)
    {
        permitted('grades.update', $request->wantsJson()); // Check permission

        try {
            $validated = $request->validated();
            $grade->update($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Grade updated successfully'])
                : redirect()->back()->with(['success' => 'Grade updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update grade: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update grade'], 500)
                : redirect()->back()->with(['error' => 'Failed to update grade']);
        }
    }

    /**
     * Remove the specified grade(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        permitted('grades.delete', true); // Check permission (JSON response)

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:grades,id',
            ]);

            $deleted = Grade::whereIn('id', $request->input('ids'))->delete();
            return response()->json([
                'message' => $deleted ? 'Grade(s) deleted successfully' : 'No grades were deleted',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete grades: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete grade(s)'], 500);
        }
    }

    /**
     * Restore a soft-deleted grade.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        permitted('grades.restore', true); // Check permission (JSON response)

        try {
            $grade = Grade::withTrashed()->findOrFail($id);
            $grade->restore();

            return response()->json(['message' => 'Grade restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore grade: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore grade'], 500);
        }
    }
}