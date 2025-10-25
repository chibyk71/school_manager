<?php

namespace App\Http\Controllers;

use App\Models\Academic\AcademicSession;
use App\Http\Requests\StoreAcademicSessionRequest;
use App\Http\Requests\UpdateAcademicSessionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing academic sessions in a multi-tenant school management system.
 *
 * Handles CRUD operations for AcademicSession models, ensuring proper authorization
 * and school scoping for multi-tenancy.
 *
 * @package App\Http\Controllers
 */
class AcademicSessionController extends Controller
{
    /**
     * Display a listing of academic sessions for the current school.
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        // Check if the user has permission to view academic sessions
        permitted('view-academic-sessions');

        try {
            // Fetch sessions for the current school, sorted by start_date descending
            $academicSessions = AcademicSession::query()
                ->orderBy('start_date', 'desc')
                ->get()
                ->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'name' => $session->name,
                        'start_date' => Carbon::parse($session->start_date)->toDateString(), // Format: YYYY-MM-DD
                        'end_date' => Carbon::parse($session->end_date)->toDateString(), // Format: YYYY-MM-DD
                        'start_date_human' => Carbon::parse($session->start_date)->translatedFormat('F j, Y'), // Format: March 11, 2025
                        'end_date_human' => Carbon::parse($session->end_date)->translatedFormat('F j, Y'), // Format: February 17, 2026
                        'is_current' => $session->is_current,
                        'school_id' => $session->school_id,
                        'created_at' => $session->created_at->toDateTimeString(),
                        'updated_at' => $session->updated_at->toDateTimeString(),
                    ];
                });

            // Render the Inertia view
            // Suggested UI file: resources/js/Pages/Academic/AcademicSession.vue
            return Inertia::render('Academic/AcademicSession', [
                'academicSessions' => $academicSessions,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch academic sessions: ' . $e->getMessage());
            return redirect()->route('academic-session.index')->with('error', 'Failed to load academic sessions.');
        }
    }

    /**
     * Store a newly created academic session.
     *
     * @param StoreAcademicSessionRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreAcademicSessionRequest $request)
    {
        // Check if the user has permission to create academic sessions
        permitted('create-academic-sessions');

        try {
            $validated = $request->validated();

            // Ensure only one session is marked as current per school
            if ($validated['is_current']) {
                AcademicSession::where('school_id', GetSchoolModel()->id)
                    ->where('is_current', true)
                    ->update(['is_current' => false]);
            }

            AcademicSession::create($validated);

            return redirect()->route('academic-session.index')
                ->with('success', 'Academic Session created successfully');
        } catch (\Exception $e) {
            Log::error('Failed to create academic session: ' . $e->getMessage());
            return redirect()->route('academic-session.index')
                ->with('error', 'Failed to create academic session.');
        }
    }

    /**
     * Update an existing academic session.
     *
     * @param UpdateAcademicSessionRequest $request
     * @param AcademicSession $academicSession
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateAcademicSessionRequest $request, AcademicSession $academicSession)
    {
        // Check if the user has permission to update academic sessions
        permitted('update-academic-sessions');

        try {
            $validated = $request->validated();

            // Ensure only one session is marked as current per school
            if ($validated['is_current']) {
                AcademicSession::where('school_id', GetSchoolModel()->id)
                    ->where('is_current', true)
                    ->update(['is_current' => false]);
            }

            $academicSession->update($validated);

            return redirect()->route('academic-session.index')
                ->with('success', 'Academic Session updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update academic session: ' . $e->getMessage());
            return redirect()->route('academic-session.index')
                ->with('error', 'Failed to update academic session.');
        }
    }

    /**
     * Delete one or more academic sessions.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        // Check if the user has permission to delete academic sessions
        permitted('delete-academic-sessions');

        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:academic_sessions,id',
            ]);

            $deleted = AcademicSession::whereIn('id', $validated['ids'])
                ->where('school_id', GetSchoolModel()->id)
                ->delete();

            if ($deleted === 0) {
                return redirect()->route('academic-session.index')
                    ->with('error', 'No academic sessions were deleted.');
            }

            return redirect()->route('academic-session.index')
                ->with('success', 'Academic Session(s) deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete academic sessions: ' . $e->getMessage());
            return redirect()->route('academic-session.index')
                ->with('error', 'Failed to delete academic sessions.');
        }
    }
}