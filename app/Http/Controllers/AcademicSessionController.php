<?php

namespace App\Http\Controllers;

use App\Models\Academic\AcademicSession;
use App\Http\Requests\StoreAcademicSessionRequest;
use App\Http\Requests\UpdateAcademicSessionRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AcademicSessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // TODO only people permited to view this page should be able to view it
        //  add a gate to check if the user has permission to view this page

        // Fetch sessions sorted by start_date in descending order
        $academicSessions = AcademicSession::orderBy('start_date', 'desc')->get()->map(function ($session) {
            return [
                'id' => $session->id,
                'name' => $session->name,
                'start_date' => Carbon::parse($session->start_date)->toDateString(), // "YYYY-MM-DD"
                'end_date' => Carbon::parse($session->end_date)->toDateString(), // "YYYY-MM-DD"
                'start_date_human' => Carbon::parse($session->start_date)->translatedFormat('F j, Y'), // "March 11, 2025"
                'end_date_human' => Carbon::parse($session->end_date)->translatedFormat('F j, Y'), // "February 17, 2026"
                'is_current' => $session->is_current,
                'school_id' => $session->school_id,
                'created_at' => $session->created_at,
                'updated_at' => $session->updated_at,
            ];
        });
        // Return to Inertia view
        return Inertia::render('Academic/AcademicSession', [
            'academicSessions' => $academicSessions
        ]);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAcademicSessionRequest $request)
    {
        $validated = $request->validated();

        // if status is true, set all other sessions to false
        if ($validated['is_current']) {
            AcademicSession::where('
            is_current', true)->update(['
            is_current' => false
                    ]);
        }

        AcademicSession::create($validated);

        return redirect()->route('academic-session.index')->with('success', 'Academic Session created successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAcademicSessionRequest $request, AcademicSession $academicSession)
    {
        $validated = $request->validated();

        // if status is true, set all other sessions to false
        if ($validated['is_current']) {
            AcademicSession::where('is_current', true)->update(['is_current' => false]);
        }

        $academicSession->update($validated);

        return redirect()->route('academic-session.index')->with('success', 'Academic Session updated successfully');

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(\Illuminate\Http\Request $request)
    {
        $deleted = AcademicSession::destroy($request->ids);

        return redirect()->route('academic-session.index')->with('success', 'Academic Session deleted successfully');
    }
}
