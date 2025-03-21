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
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClassLevelRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ClassLevel $classLevel)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ClassLevel $classLevel)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClassLevelRequest $request, ClassLevel $classLevel)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ClassLevel $classLevel)
    {
        //
    }
}
