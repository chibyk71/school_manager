<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClassLevelRequest;
use App\Http\Requests\UpdateClassLevelRequest;
use App\Models\Academic\ClassLevel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClassLevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // return Inertia::render('Academic/ClassLevels'); or json response is json is requested
        $classLevels = ClassLevel::with('schoolSection:id,name')->get(['id', 'name', 'display_name', 'description', 'school_section_id']);


        if ($request->wantsJson()) {
            return response()->json($classLevels);
        }

        return Inertia::render('Academic/ClassLevels', [
            'classLevels' => $classLevels
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClassLevelRequest $request)
    {
        $validated = $request->validated();

        ClassLevel::create($validated);

        return redirect()->back()->with(['success' => 'Class level created successfully']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClassLevelRequest $request, ClassLevel $classLevel)
    {
        $validated = $request->validated();

        $classLevel->update($validated);

        return redirect()->back()->with(['success' => 'Class level updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        if ($request->has('ids')) {
            // Delete multiple resources
            $deleted = ClassLevel::whereIn('id', $request->ids)->delete();

            return response()->json(['message' => 'Class levels deleted successfully']);
        }

        return response()->json(['message' => 'No class levels were deleted']);
    }
}
