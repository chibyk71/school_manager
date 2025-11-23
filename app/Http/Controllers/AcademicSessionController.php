<?php

namespace App\Http\Controllers;

use App\Models\Academic\AcademicSession;
use App\Http\Requests\StoreAcademicSessionRequest;
use App\Http\Requests\UpdateAcademicSessionRequest;
use App\Services\AcademicSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
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

    public function __construct(protected AcademicSessionService $service)
    {
    }

    /**
     * Display a listing of academic sessions for the current school.
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        // Check if the user has permission to view academic sessions
        Gate::authorize('viewAny', AcademicSession::class);

        try {

            $extra = [
                ['field' => 'term_count', 'relation' => 'terms', 'aggregate' => 'count', 'sortable' => true],
            ];

            $sessions = AcademicSession::tableQuery($request, $extra)->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'start_date' => $s->start_date,
                'end_date' => $s->end_date,
                'is_current' => $s->is_current,
                'term_count' => $s->terms_count ?? 0,
                'created_at' => $s->created_at->toDateTimeString(),
            ]);

            // Render the Inertia view
            // Suggested UI file: resources/js/Pages/Academic/AcademicSession.vue
            return Inertia::render('Academic/AcademicSession', [
                'sessions' => $sessions,
                'can' => [
                    'create' => $request->user()->can('create', AcademicSession::class),
                    'delete' => $request->user()->can('delete', AcademicSession::class),
                ]
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
        Gate::authorize('create', AcademicSession::class);

        try {
            $validated = $request->validated();

            // Auto-deactivate previous current session if needed
            if ($validated['is_current'] ?? false) {
                $this->service->setCurrentSession(
                    AcademicSession::create($validated)
                );
            } else {
                AcademicSession::create($validated);
            }

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
        Gate::authorize('update', $academicSession);

        try {
            $validated = $request->validated();

            // Ensure only one session is marked as current per school
            if ($validated['is_current']) {
                $this->service->setCurrentSession($academicSession);
                unset($validated['is_current']);
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


    /** Set current (quick toggle) */
    public function setCurrent(AcademicSession $session)
    {
        Gate::authorize('update', $session);
        $this->service->setCurrentSession($session);

        return back()->with('success', 'Current session switched.');
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
        Gate::authorize('delete', AcademicSession::class);

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
