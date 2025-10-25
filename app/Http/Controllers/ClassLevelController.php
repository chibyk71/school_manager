<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClassLevelRequest;
use App\Http\Requests\UpdateClassLevelRequest;
use App\Models\Academic\ClassLevel;
use App\Models\SchoolSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing ClassLevel resources.
 */
class ClassLevelController extends Controller
{
    /**
     * Display a listing of class levels with dynamic querying.
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        permitted('class-levels.view', $request->wantsJson()); // Check permission

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

            // Apply dynamic table query (search, filter, sort, paginate)
            $classLevels = ClassLevel::with('schoolSection:id,name')
                ->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($classLevels);
            }

            return Inertia::render('Academic/ClassLevels', [
                'classLevels' => $classLevels,
                'schoolSections' => SchoolSection::select('id', 'name')->get(), // For dropdowns in UI
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch class levels: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch class levels'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch class levels']);
        }
    }

    /**
     * Store a newly created class level in storage.
     *
     * @param StoreClassLevelRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreClassLevelRequest $request)
    {
        permitted('class-levels.create', $request->wantsJson()); // Check permission

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id; // Ensure school_id is set
            ClassLevel::create($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Class level created successfully'], 201)
                : redirect()->back()->with(['success' => 'Class level created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create class level: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create class level'], 500)
                : redirect()->back()->with(['error' => 'Failed to create class level']);
        }
    }

    /**
     * Update the specified class level in storage.
     *
     * @param UpdateClassLevelRequest $request
     * @param ClassLevel $classLevel
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateClassLevelRequest $request, ClassLevel $classLevel)
    {
        permitted('class-levels.update', $request->wantsJson()); // Check permission

        try {
            $validated = $request->validated();
            $classLevel->update($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Class level updated successfully'])
                : redirect()->back()->with(['success' => 'Class level updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update class level: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update class level'], 500)
                : redirect()->back()->with(['error' => 'Failed to update class level']);
        }
    }

    /**
     * Remove the specified class level(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        permitted('class-levels.delete', true); // Check permission (JSON response)

        try {
            if ($request->has('ids')) {
                $deleted = ClassLevel::whereIn('id', $request->input('ids'))->delete();
                return response()->json([
                    'message' => $deleted ? 'Class levels deleted successfully' : 'No class levels were deleted',
                ]);
            }

            return response()->json(['message' => 'No class levels were deleted'], 400);
        } catch (\Exception $e) {
            Log::error('Failed to delete class levels: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete class levels'], 500);
        }
    }

    /**
     * Restore a soft-deleted class level.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        permitted('class-levels.restore', true); // Check permission (JSON response)

        try {
            $classLevel = ClassLevel::withTrashed()->findOrFail($id);
            $classLevel->restore();

            return response()->json(['message' => 'Class level restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore class level: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore class level'], 500);
        }
    }
}