<?php

namespace App\Http\Controllers;

use App\Models\SchoolSection;
use App\Http\Requests\StoreSchoolSectionRequest;
use App\Http\Requests\UpdateSchoolSectionRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SchoolSectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $schoolSections = SchoolSection::with('school:id,name')->get();

        return Inertia::render('Academic/Section', [
            "sections" => $schoolSections
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSchoolSectionRequest $request)
    {
        try {
            $validated = $request->validated();

            $schoolSection = SchoolSection::create($validated);

            if ($validated['school_id']) {
                $schoolSection->school()->associate($validated['school_id']);
                $schoolSection->save();
            }

            // return redirect()->route('sections.index');
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create school section.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(SchoolSection $schoolSection)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSchoolSectionRequest $request, SchoolSection $schoolSection)
    {
        try {
            $validated = $request->validated();
            $schoolSection->update($validated);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update school section.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!empty($ids)) {
            try {
                SchoolSection::destroy($ids);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Failed to delete school sections.',
                    'message' => $e->getMessage()
                ], 500);
            }
        }
    }
}
