<?php

namespace App\Http\Controllers;

use App\Models\SchoolSection;
use App\Http\Requests\StoreSchoolSectionRequest;
use App\Http\Requests\UpdateSchoolSectionRequest;
use Inertia\Inertia;

class SchoolSectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('Academic/Section');
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
    public function store(StoreSchoolSectionRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(SchoolSection $schoolSection)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SchoolSection $schoolSection)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSchoolSectionRequest $request, SchoolSection $schoolSection)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SchoolSection $schoolSection)
    {
        //
    }
}
