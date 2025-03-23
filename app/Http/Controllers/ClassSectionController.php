<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClassSectionRequest;
use App\Http\Requests\UpdateClassSectionRequest;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClassSectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(?ClassLevel $classLevel = null)
    {
        // if class level is not null, return the class level display name and id, with the class sections of the class level
        if ($classLevel) {
            return Inertia::render('Academic/ClassSections', [
                'classLevel' => $classLevel->only('id', 'display_name'),
                'classSections' => $classLevel->classSections()->with('students')->get(),
            ]);
        }

        // if class level is null, return all class sections with their class levels and students
        return Inertia::render('Academic/ClassSections', [
            'classSections' => ClassSection::with('classLevel', 'students')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClassSectionRequest $request)
    {
        ClassSection::create($request->validated());

        return back()->with(['success' => 'Class Section created successfully']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClassSectionRequest $request, ClassSection $classSection)
    {
        $classSection->update($request->validated());

        return back()->with(['success' => 'Class Section updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:class_sections,id',
        ]);

        try {
            ClassSection::destroy($request->ids);
            return response()->json(['message' => 'Class Section(s) deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete Class Section(s)', 'details' => $e->getMessage()], 500);
        }
    }
}
