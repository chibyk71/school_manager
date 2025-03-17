<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolRequest;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
/**
 * Only Admins or those with permission to manage this feature should be allowed to access this controller.
*/

class SchoolController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Gate::authorize('viewAny', School::class);

        $schools = School::all();

        return Inertia::render('Academic/School', [
            'schools' => $schools
        ]);
    }

    /**
     * Store a newly created resource in storage.
    */
    public function store(StoreSchoolRequest $request)
    {
        Gate::authorize('create', School::class);

        // validate the request data
        $validated = $request->validated();

        School::create($validated);
    }

    /**
     * Display the specified resource.
     */
    public function show(School $school)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSchoolRequest $request, School $school)
    {
        $school->update($request->validated());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(School $school)
    {
        $school->delete();
    }
}
