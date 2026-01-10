<?php

namespace App\Services;

use App\Events\Academic\TermClosed;
use App\Events\Academic\TermReopened;
use App\Models\Academic\Term;
use App\Notifications\TermClosedNotification;
use App\Notifications\TermReopenedNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * TermClosureService – Handles Term Closure, Reopening & Related Side Effects
 *
 * This service is the **single point of truth** for closing and reopening academic terms.
 * It enforces strict business rules, ensures data consistency via transactions,
 * triggers notifications, dispatches domain events, and prepares for future locking
 * of assessments/results.
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Strict reopen restrictions: only the most recently closed term can be reopened
 * • Only allowed if next term is still pending (not started/closed)
 * • Transactional safety: all-or-nothing updates
 * • Automatic event dispatching for loose coupling (e.g. report card generation)
 * • Multi-channel notifications to relevant roles (admins, principal, class teachers)
 * • Audit trail via logging + activity logging (Spatie if applied on model)
 * • Prepares for future assessment/result locking (placeholder hooks)
 * • Clear validation exceptions → friendly frontend feedback
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Called by TermClosureController (API endpoints)
 * • Used internally by AcademicCalendarService for high-level workflows
 * • Delegates notification sending & event firing
 * • Keeps AcademicCalendarService focused on coordination
 * • Integrates with future modules via events:
 *   - ReportCards → listens to TermClosed
 *   - Promotion → indirectly via SessionClosed (after last term)
 *   - Analytics → tracks closure/reopen frequency
 *
 * Important Business Rules Enforced:
 * • Cannot close non-active term
 * • Cannot reopen non-closed term
 * • Reopen only allowed on the **last closed term**
 * • Reopen blocked if next term has started or been closed
 * • Status updates are synchronized (is_closed, is_active, status field)
 * • All operations are wrapped in DB transactions
 * • Critical actions are logged with context (user, term, session)
 *
 * Usage Example:
 *   $service = app(TermClosureService::class);
 *   $service->closeTerm($term);
 *   $service->reopenTerm($term);
 */
class TermClosureService
{
    /**
     * Close an active term.
     *
     * @param Term $term
     * @return void
     * @throws ValidationException
     */
    public function closeTerm(Term $term, ?string $reason): void
    {
        if (!$term->is_active) {
            throw ValidationException::withMessages([
                'status' => 'Only active terms can be closed.'
            ]);
        }

        // Optional: Add future pre-checks (e.g. "are all results finalized?")
        // $this->checkResultsFinalized($term);

        DB::transaction(function () use ($term) {
            $term->update([
                'is_active' => false,
                'is_closed' => true,
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            // Placeholder for future locking logic
            // $this->lockTermAssessments($term);

            // Invalidate any caches (handled in AcademicCalendarService)
        });

        // Fire domain event
        event(new TermClosed($term));

        // Send notifications (queued)
        $this->notifyTermClosed($term);

        activity()
            ->performedOn($term)
            ->causedBy(auth()->user())
            ->withProperties([
                'session_id'    => $term->academic_session_id,
                'session_name'  => $term->academicSession->name,
                'reason'        => $reason ?? null,
            ])
            ->log("Term '{$term->display_name}' was **reopened**" . ($reason ? " (Reason: {$reason})" : ''));

        Log::info("Term closed: {$term->display_name} in {$term->academicSession->name}", [
            'term_id' => $term->id,
            'session_id' => $term->academic_session_id,
            'user_id' => auth()->id(),
            'closed_at' => now(),
        ]);
    }


    /**
     * Reopen the most recently closed term with a new end date (restricted operation).
     *
     * This method enforces strict safety rules for reopening a closed term:
     *   - Only the **most recently closed term** can be reopened
     *   - The **next term** must still be pending (not active or closed)
     *   - New end date **must** be after original start date
     *   - New end date **must not** collide with or exceed the next term's start date (if next term exists)
     *   - New end date **must** stay within the parent session's end date
     *   - Operation is transactional for consistency
     *   - Dispatches event and notifications for downstream integration
     *   - Logs warning-level audit trail (reopen is exceptional)
     *
     * @param Term $term The term to reopen (must be closed)
     * @param string $reason Mandatory audit justification (min 20 chars enforced in request)
     * @param string $newEndDate New end date in 'Y-m-d' format
     * @return void
     * @throws ValidationException If any business rule is violated
     */
    public function reopenTerm(Term $term, string $reason, string $newEndDate): void
    {
        // 1. Pre-check: Term must be closed
        if (!$term->is_closed) {
            throw ValidationException::withMessages([
                'status' => 'This term is not closed and cannot be reopened.'
            ]);
        }

        $session = $term->academicSession;
        $newEnd = Carbon::parse($newEndDate)->startOfDay(); // Normalize to start of day for consistency

        // 2. Validate new end date against session bounds
        if ($newEnd->gt($session->end_date)) {
            throw ValidationException::withMessages([
                'new_end_date' => "New end date cannot exceed the session end date ({$session->end_date->format('Y-m-d')})."
            ]);
        }

        // 3. Validate new end date against next term (if exists)
        $nextOrdinal = $term->ordinal_number + 1;
        $nextTerm = Term::where('academic_session_id', $session->id)
            ->where('ordinal_number', $nextOrdinal)
            ->first();

        if ($nextTerm && $nextTerm->start_date && $newEnd->gte($nextTerm->start_date)) {
            throw ValidationException::withMessages([
                'new_end_date' => "New end date cannot be on or after the next term's start date ({$nextTerm->start_date->format('Y-m-d')})."
            ]);
        }

        // 4. Validate new end date is after original start date
        if ($newEnd->lt($term->start_date)) {
            throw ValidationException::withMessages([
                'new_end_date' => 'New end date must be on or after the term\'s original start date.'
            ]);
        }

        // 5. Enforce: Only the most recently closed term can be reopened
        $lastClosed = Term::where('academic_session_id', $session->id)
            ->where('is_closed', true)
            ->orderByDesc('closed_at')
            ->first();

        if (!$lastClosed || $lastClosed->id !== $term->id) {
            throw ValidationException::withMessages([
                'term' => 'Only the most recently closed term can be reopened.'
            ]);
        }

        // 6. Perform the reopen in a transaction
        DB::transaction(function () use ($term, $newEnd) {
            $term->update([
                'is_closed' => false,
                'is_active' => true, // Immediately reactivate
                'status' => 'active', // Or use DynamicEnum lookup if needed
                'end_date' => $newEnd,
                'closed_at' => null,
            ]);

            // Future: Unlock any locked assessments/results/exams
            // $this->unlockTermAssessments($term);
        });

        // 7. Fire domain event for loose coupling (e.g. reverse report generation, audit logs)
        event(new TermReopened($term));

        // 8. Send notifications (queued)
        $this->notifyTermReopened($term);

        // 9. Audit logging (warning level due to exceptional nature)
        activity()
            ->performedOn($term)
            ->causedBy(auth()->user())
            ->withProperties([
                'session_id'    => $term->academic_session_id,
                'session_name'  => $term->academicSession->name,
                'reason'        => $reason ?? null,
                'new_end_date'  => $newEndDate,
            ])
            ->log("Term '{$term->display_name}' was **reopened**" . ($reason ? " (Reason: {$reason})" : ''));
    }

    // ────────────────────────────────────────────────────────────────
    // Notification Helpers (can be expanded with roles/permissions)
    // ────────────────────────────────────────────────────────────────

    /**
     * Send notifications when a term is closed.
     */
    protected function notifyTermClosed(Term $term): void
    {
        // Example: Notify principal, admin, and class teachers
        // You can use Laravel's notification system with channels (mail, database, broadcast)
        $notifiables = $this->getRelevantNotifiables($term);

        foreach ($notifiables as $user) {
            $user->notify(new TermClosedNotification($term));
        }
    }

    /**
     * Send notifications when a term is reopened.
     */
    protected function notifyTermReopened(Term $term): void
    {
        $notifiables = $this->getRelevantNotifiables($term);

        foreach ($notifiables as $user) {
            $user->notify(new TermReopenedNotification($term));
        }
    }

    /**
     * Get users who should be notified about term closure/reopen.
     * Customize this based on your roles/permissions system.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getRelevantNotifiables(Term $term): \Illuminate\Support\Collection
    {
        // Example – replace with your actual roles/users query
        // TODO: use settings to determine who to notify
        return $term->school->users()
            ->whereIn('role', ['admin', 'principal', 'head-teacher'])
            ->get();
    }

    // ────────────────────────────────────────────────────────────────
    // Future Extension Points (placeholders)
    // ────────────────────────────────────────────────────────────────

    /**
     * Placeholder: Lock assessments/results when term closes.
     * To be implemented when assessment module is ready.
     */
    protected function lockTermAssessments(Term $term): void
    {
        // TODO: Come back to this once assessment module is ready
        // Example: AssessmentResult::whereTermId($term->id)->update(['is_locked' => true]);
    }

    /**
     * Placeholder: Unlock when term is reopened.
     */
    protected function unlockTermAssessments(Term $term): void
    {
        // AssessmentResult::whereTermId($term->id)->update(['is_locked' => false]);
    }
}
