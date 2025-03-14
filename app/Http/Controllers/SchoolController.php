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
        Gate::authorize('viewAny');

        $schools = School::all();

        return Inertia::render('Academic/School', [
            'schools' => $schools
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
    public function store(StoreSchoolRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(School $school)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(School $school)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSchoolRequest $request, School $school)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(School $school)
    {
        //
    }
}
