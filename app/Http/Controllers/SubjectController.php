<?php

namespace App\Http\Controllers;

use App\Models\Academic\Subject;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('Academic/Subjects');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'code' => 'required|string',
            'credit' => 'nullable|numeric',
            'is_elective' => 'required|boolean',
            'school_section_ids' => 'required|array',
            'school_section_ids.*' => 'required|exists:school_sections,id'
        ]);

        try {
            $subject = Subject::create($validated);
            $subject->attachSections($validated['school_section_ids']);
            return redirect()->route('subject.index')->with('success', 'Subject created.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'An error occurred while creating the subject. Please try again.'])->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Subject $subject)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Subject $subject)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Subject $subject)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subject $subject)
    {
        //
    }
}
