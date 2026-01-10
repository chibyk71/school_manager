<?php

namespace App\Http\Controllers\Settings\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseTermRequest;
use App\Http\Requests\ReopenTermRequest;
use App\Models\Academic\Term;
use App\Services\TermClosureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * TermClosureController – Specialized Controller for Term Closure & Reopen Actions
 *
 * Handles the two most sensitive lifecycle operations for academic terms:
 *   - Closing an active term (locks it for editing/results finalization)
 *   - Reopening a previously closed term (restricted, with new end date & mandatory reason)
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Dedicated, RESTful endpoints for state transitions (PATCH /close and /reopen)
 * • Strict policy-based authorization ('terms.close' and 'terms.reopen')
 * • Full input validation via dedicated request classes (reason, new_end_date)
 * • Complete delegation of business logic to TermClosureService
 *   (transactions, date collision checks, events, notifications, logging)
 * • Multi-tenant safety: all operations implicitly scoped via policy/service
 * • Inertia-friendly redirects with flash messages (success/error)
 * • Comprehensive error handling + detailed logging (audit trail)
 * • Production-ready: no silent failures, clear user feedback, input preservation
 * • Future-ready: easy to add workflow (e.g. principal approval step)
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Handles the most critical & restricted term operations
 * • Called from frontend confirmation dialogs:
 *   - TermCloseDialog.vue (optional reason)
 *   - TermReopenConfirmation.vue (mandatory reason + new end date picker)
 * • Complements TermController (regular CRUD) by focusing exclusively on state changes
 * • Integrates with TermClosureService (core rules) & Term model (state flags)
 * • Triggers notifications: TermClosedNotification, TermReopenedNotification
 * • Dispatches domain events: TermClosed, TermReopened (for future integration)
 * • Aligns with frontend stack: Inertia redirects + flash messages for UX
 *
 * Routes (add to routes/web.php):
 *   PATCH  /terms/{term}/close     → close
 *   PATCH  /terms/{term}/reopen    → reopen
 *
 * Security & Permission Notes:
 *   - 'terms.close': typically granted to principal/admin
 *   - 'terms.reopen': highly restricted (principal + super-admin recommended)
 *   - All actions logged with full context (term, session, user, reason, new date)
 *   - Cannot close non-active terms or reopen non-last-closed terms (service enforces)
 */
class TermClosureController extends Controller
{
    public function __construct(protected TermClosureService $service)
    {
        // Optional middleware for extra protection (if needed beyond policy)
        // $this->middleware('permission:terms.manage-critical')->only(['close', 'reopen']);
    }

    /**
     * Close an active term.
     *
     * PATCH /terms/{term}/close
     *
     * Accepts optional reason for audit trail.
     * Triggers locking, event, notifications, and logging.
     */
    public function close(CloseTermRequest $request, Term $term)
    {
        Gate::authorize('close', $term);

        try {
            $validated = $request->validated();

            // Delegate to service (handles transaction, event, notifications, locking)
            $this->service->closeTerm($term, $validated['reason'] ?? null);

            return redirect()
                ->route('terms.index', ['academicSession' => $term->academic_session_id])
                ->with('success', "Term '{$term->display_name}' has been closed successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to close term', [
                'error' => $e->getMessage(),
                'term_id' => $term->id,
                'session_id' => $term->academic_session_id,
                'user_id' => auth()->id() ?? 'system',
                'reason' => $request->input('reason'),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to close term: ' . ($e->getMessage() ?? 'An unexpected error occurred.'));
        }
    }

    /**
     * Reopen a previously closed term with a new end date.
     *
     * PATCH /terms/{term}/reopen
     *
     * Restricted operation requiring:
     *   - Mandatory reason (audit justification)
     *   - Valid new end date (no collision with next term or session bounds)
     *   - Term must be the most recently closed one
     */
    public function reopen(ReopenTermRequest $request, Term $term)
    {
        Gate::authorize('reopen', $term);

        try {
            $validated = $request->validated();

            // Delegate to service (handles validation, transaction, event, notifications)
            $this->service->reopenTerm(
                $term,
                $validated['reason'],
                $validated['new_end_date']
            );

            return redirect()
                ->route('terms.index', ['academicSession' => $term->academic_session_id])
                ->with('success', "Term '{$term->display_name}' has been reopened successfully with new end date.");
        } catch (\Exception $e) {
            Log::error('Failed to reopen term', [
                'error' => $e->getMessage(),
                'term_id' => $term->id,
                'session_id' => $term->academic_session_id,
                'user_id' => auth()->id() ?? 'system',
                'reason' => $request->input('reason'),
                'new_end_date' => $request->input('new_end_date'),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to reopen term: ' . ($e->getMessage() ?? 'An unexpected error occurred.'));
        }
    }
}
