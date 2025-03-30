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
        return Inertia::render('Academic/Subjects', [
            'subjects' => Subject::with('schoolSections:id,display_name')
                // If the request has a school section query parameter, filter
                // the subjects to only include those in that school section.
                ->when(request('school_section'), function ($query) {
                    $query->inSection(request('school_section'));
                })
                // If the request has a search query parameter, filter the subjects
                // to only include those with a name or code that contains the
                // search term.
                ->when(request('search'), function ($query) {
                    $query->where(function ($q) {
                        $q->where('name', 'like', '%' . request('search') . '%')
                            ->orWhere('code', 'like', '%' . request('search') . '%');
                    });
                })
                // Order the subjects by creation date, most recent first.
                ->orderByDesc('created_at')
                // If the request has a with_trashed query parameter, include
                // trashed subjects in the result.
                ->when(request()->boolean('with_trashed'), function ($query) {
                    $query->withTrashed();
                })
                // Finally, get the subjects from the database.
                ->get()
        ]);
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
            'school_section' => 'required|array',
            'school_section.*' => 'required|exists:school_sections,id'
        ]);

        try {
            $subject = Subject::create($validated);
            $subject->attachSections($validated['school_section']);
            return redirect()->route('subject.index')->with('success', 'Subject created.');
        } catch (\Exception $e) {
            logger($e);
            return back()->withErrors(['error' => 'An error occurred while creating the subject. Please try again.'])->withInput();
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Subject $subject)
    {
        // Validate the request
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'code' => 'required|string',
            'credit' => 'nullable|numeric',
            'is_elective' => 'required|boolean',
            'school_section' => 'required|array',
            'school_section.*' => 'required|exists:school_sections,id'
        ]);

        try {
            $subject->update($validated);
            $subject->syncSections($validated['school_section']);
            return redirect()->route('subject.index')->with('success', 'Subject updated.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'An error occurred while updating the subject. Please try again.'])->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        // Check if the request wants to force delete
        $forceDelete = request()->boolean('force');

        // Delete the subjects and return a response
        if ($forceDelete) {
            Subject::whereIn('id', request('ids'))->forceDelete();
        } else {
            Subject::whereIn('id', request('ids'))->delete();
        }

        return response()->json(['message' => 'Subjects deleted.']);
    }
}
