<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClassSectionRequest;
use App\Http\Requests\UpdateClassSectionRequest;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing ClassSection resources.
 */
class ClassSectionController extends Controller
{
    /**
     * Display a listing of class sections with dynamic querying.
     *
     * @param Request $request
     * @param ClassLevel|null $classLevel
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request, ?ClassLevel $classLevel = null)
    {
        Gate::authorize('viewAny', ClassSection::class); // Policy-based authorization

        try {
            // Define extra fields for table query (e.g., related class level name)
            $extraFields = [
                [
                    'field' => 'class_level_name',
                    'relation' => 'classLevel',
                    'relatedField' => 'display_name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            // Build query
            $query = ClassSection::with(['classLevel:id,display_name', 'students:id,first_name,last_name'])
                ->when($classLevel, fn($q) => $q->forClassLevel($classLevel->id));

            // Apply dynamic table query (search, filter, sort, paginate)
            $classSections = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($classSections);
            }

            return Inertia::render('Academic/ClassSections', [
                'classLevel' => $classLevel ? $classLevel->only('id', 'display_name') : null,
                'classSections' => $classSections,
                'classLevels' => ClassLevel::select('id', 'display_name')->get(), // For dropdowns in UI
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch class sections: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch class sections'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch class sections']);
        }
    }

    /**
     * Store a newly created class section in storage.
     *
     * @param StoreClassSectionRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreClassSectionRequest $request)
    {
        Gate::authorize('create', ClassSection::class); // Policy-based authorization

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id; // Ensure school_id is set
            ClassSection::create($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Class section created successfully'], 201)
                : redirect()->back()->with(['success' => 'Class section created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create class section: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create class section'], 500)
                : redirect()->back()->with(['error' => 'Failed to create class section']);
        }
    }

    /**
     * Display the specified class section.
     *
     * @param Request $request
     * @param ClassSection $classSection
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, ClassSection $classSection)
    {
        Gate::authorize('view', $classSection); // Policy-based authorization

        try {
            $classSection->load(['classLevel:id,display_name', 'students:id,first_name,last_name']);
            return response()->json($classSection);
        } catch (\Exception $e) {
            Log::error('Failed to fetch class section: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch class section'], 500);
        }
    }

    /**
     * Update the specified class section in storage.
     *
     * @param UpdateClassSectionRequest $request
     * @param ClassSection $classSection
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateClassSectionRequest $request, ClassSection $classSection)
    {
        Gate::authorize('update', $classSection); // Policy-based authorization

        try {
            $validated = $request->validated();
            $classSection->update($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Class section updated successfully'])
                : redirect()->back()->with(['success' => 'Class section updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update class section: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update class section'], 500)
                : redirect()->back()->with(['error' => 'Failed to update class section']);
        }
    }

    /**
     * Remove the specified class section(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', ClassSection::class); // Policy-based authorization

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:class_sections,id',
            ]);

            $deleted = ClassSection::whereIn('id', $request->input('ids'))->delete();
            return response()->json([
                'message' => $deleted ? 'Class section(s) deleted successfully' : 'No class sections were deleted',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete class sections: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete class section(s)', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Restore a soft-deleted class section.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $classSection = ClassSection::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $classSection); // Policy-based authorization

        try {
            $classSection->restore();
            return response()->json(['message' => 'Class section restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore class section: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore class section'], 500);
        }
    }
}