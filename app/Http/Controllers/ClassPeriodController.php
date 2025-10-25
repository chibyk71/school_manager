<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClassPeriodRequest;
use App\Http\Requests\UpdateClassPeriodRequest;
use App\Models\Academic\ClassPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing ClassPeriod resources.
 */
class ClassPeriodController extends Controller
{
    /**
     * Display a listing of class periods with dynamic querying.
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        permitted('class-periods.view', $request->wantsJson()); // Check permission

        try {
            // Define extra fields for table query (if any)
            $extraFields = [];

            // Apply dynamic table query (search, filter, sort, paginate)
            $classPeriods = ClassPeriod::tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($classPeriods);
            }

            return Inertia::render('Academic/ClassPeriods', [
                'classPeriods' => $classPeriods,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch class periods: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch class periods'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch class periods']);
        }
    }

    /**
     * Store a newly created class period in storage.
     *
     * @param StoreClassPeriodRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreClassPeriodRequest $request)
    {
        permitted('class-periods.create', $request->wantsJson()); // Check permission

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id; // Ensure school_id is set
            ClassPeriod::create($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Class period created successfully'], 201)
                : redirect()->back()->with(['success' => 'Class period created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create class period: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create class period'], 500)
                : redirect()->back()->with(['error' => 'Failed to create class period']);
        }
    }

    /**
     * Display the specified class period.
     *
     * @param ClassPeriod $classPeriod
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, ClassPeriod $classPeriod)
    {
        permitted('class-periods.view', true); // Check permission (JSON response)

        try {
            return response()->json($classPeriod);
        } catch (\Exception $e) {
            Log::error('Failed to fetch class period: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch class period'], 500);
        }
    }

    /**
     * Update the specified class period in storage.
     *
     * @param UpdateClassPeriodRequest $request
     * @param ClassPeriod $classPeriod
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateClassPeriodRequest $request, ClassPeriod $classPeriod)
    {
        permitted('class-periods.update', $request->wantsJson()); // Check permission

        try {
            $validated = $request->validated();
            $classPeriod->update($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Class period updated successfully'])
                : redirect()->back()->with(['success' => 'Class period updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update class period: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update class period'], 500)
                : redirect()->back()->with(['error' => 'Failed to update class period']);
        }
    }

    /**
     * Remove the specified class period(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        permitted('class-periods.delete', true); // Check permission (JSON response)

        try {
            if ($request->has('ids')) {
                $deleted = ClassPeriod::whereIn('id', $request->input('ids'))->delete();
                return response()->json([
                    'message' => $deleted ? 'Class periods deleted successfully' : 'No class periods were deleted',
                ]);
            }

            return response()->json(['message' => 'No class periods were deleted'], 400);
        } catch (\Exception $e) {
            Log::error('Failed to delete class periods: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete class periods'], 500);
        }
    }

    /**
     * Restore a soft-deleted class period.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        permitted('class-periods.restore', true); // Check permission (JSON response)

        try {
            $classPeriod = ClassPeriod::withTrashed()->findOrFail($id);
            $classPeriod->restore();

            return response()->json(['message' => 'Class period restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore class period: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore class period'], 500);
        }
    }
}