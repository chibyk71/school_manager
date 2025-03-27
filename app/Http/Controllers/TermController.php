<?php

namespace App\Http\Controllers;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Http\Requests\StoreTermRequest;
use App\Http\Requests\UpdateTermRequest;

class TermController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(?AcademicSession $academicSession = null)
    {
        // is academic session is null, get the current academic session
        if (!$academicSession) {
            $academicSession = AcademicSession::currentSession();
        }

        $terms = $academicSession->terms;
        // return a json response
        return response()->json($terms);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTermRequest $request)
    {
        $validated = $request->validated();

        // if status is active set, unset the active term
        if ($validated['status'] == 'active') {
            Term::where('status', 'active')->update(['status' => 'pending']);
        }

        // create a new term
        $term = Term::create($validated);

        return back()->with('success', 'Term created successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTermRequest $request, Term $term)
    {
        $validated = $request->validated();

        // if only status is submitted to mark as active
        if (isset($validated['status']) && $validated['status'] == 'active' && count($validated) === 1) {
            Term::where('status', 'active')->update(['status' => 'pending']);
            $term->update(['status' => 'active']);

            return back()->with('success', 'Term status updated to active successfully');
        }

        // if status is active set, unset the active term
        if (isset($validated['status']) && $validated['status'] == 'active') {
            Term::where('status', 'active')->update(['status' => 'pending']);
        }

        // update the term
        $term->update($validated);

        return back()->with('success', 'Term updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        $ids = request('ids');

        // Ensure ids is an array
        if (!is_array($ids)) {
            return back()->withErrors(['error' => 'Invalid input.']);
        }

        // Delete the terms
        Term::whereIn('id', $ids)->delete();

        return back()->with('success', 'Terms deleted successfully');
    }
}
