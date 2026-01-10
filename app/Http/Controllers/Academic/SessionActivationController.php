<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicSession;
use App\Services\AcademicCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * SessionActivationController – Handles Activation & Closure of Academic Sessions
 *
 * Specialized controller for the sensitive state-changing actions of activating
 * and closing academic sessions. These operations have significant downstream
 * effects (e.g. marking the session as current, triggering notifications,
 * preparing for promotion/reporting in future modules) and are highly restricted.
 *
 * Why this controller is still needed (even after AcademicSessionController):
 * ────────────────────────────────────────────────────────────────
 * • Activation/closure are **special lifecycle events**, not regular CRUD
 * • They require strict single-active-session enforcement (handled by service)
 * • They trigger domain events & notifications (SessionActivated, SessionClosed)
 * • They often need extra confirmation (e.g. dialogs with warnings)
 * • Keeps AcademicSessionController focused on basic CRUD (create/update/delete)
 * • Cleaner separation of concerns → easier testing & maintenance
 * • Aligns with frontend UX: separate dialogs for "Activate Session" & "Close Session"
 * • Production-ready: policy checks, logging, flash messages, Inertia support
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Called from frontend dialogs: SessionActivationDialog.vue & SessionClosureConfirmation.vue
 * • Integrates tightly with AcademicCalendarService (business rules & invariants)
 * • Complements AcademicSessionController (regular CRUD) by focusing on state transitions
 * • Triggers notifications (SessionActivatedNotification, SessionClosedNotification)
 * • Dispatches events for loose coupling (e.g. future Promotion module listens to SessionClosed)
 * • Aligns with frontend stack: Inertia redirects + flash messages
 *
 * Routes (suggested – add to routes/web.php):
 *   PATCH  /academic-sessions/{session}/activate    → activate
 *   PATCH  /academic-sessions/{session}/close       → close
 *
 * Security Notes:
 * • 'update' permission used (or create separate 'activate-session', 'close-session' perms later)
 * • All actions logged with full context (session, user)
 * • Cannot activate closed/archived sessions (service enforces)
 */
class SessionActivationController extends Controller
{
    public function __construct(protected AcademicCalendarService $service)
    {
        // Optional: Apply middleware for sensitive operations
        // $this->middleware('permission:academic-sessions.activate')->only('activate');
        // $this->middleware('permission:academic-sessions.close')->only('close');
    }

    /**
     * Activate the specified academic session (make it current/active).
     *
     * PATCH /academic-sessions/{session}/activate
     */
    public function activate(Request $request, AcademicSession $academicSession)
    {
        Gate::authorize('update', $academicSession); // or custom 'activate-session' perm

        try {
            // Service enforces single active session + immutability rules
            $this->service->activateSession($academicSession);

            return redirect()
                ->route('academic-sessions.index')
                ->with('success', "Academic session '{$academicSession->name}' has been activated and is now current.");
        } catch (\Exception $e) {
            Log::error('Failed to activate academic session', [
                'error'     => $e->getMessage(),
                'session_id' => $academicSession->id,
                'user_id'   => auth()->id(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to activate academic session. ' . ($e->getMessage() ?? 'Please try again.'));
        }
    }

    /**
     * Close the specified academic session.
     *
     * PATCH /academic-sessions/{session}/close
     */
    public function close(Request $request, AcademicSession $academicSession)
    {
        Gate::authorize('update', $academicSession); // or custom 'close-session' perm

        try {
            // Service handles closure logic, event, notifications
            $this->service->closeSession($academicSession);

            return redirect()
                ->route('academic-sessions.index')
                ->with('success', "Academic session '{$academicSession->name}' has been closed successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to close academic session', [
                'error'     => $e->getMessage(),
                'session_id' => $academicSession->id,
                'user_id'   => auth()->id(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to close academic session. ' . ($e->getMessage() ?? 'Please try again.'));
        }
    }
}
