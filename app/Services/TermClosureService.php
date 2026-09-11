<?php

namespace App\Services;

use App\Events\Academic\TermClosed;
use App\Events\Academic\TermReopened;
use App\Models\Academic\Term;
use App\Notifications\TermClosedNotification;
use App\Notifications\TermReopenedNotification;
use App\States\Academic\Term\Active as TermActive;
use App\States\Academic\Term\Closed as TermClosedState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * TermClosureService – Handles Term Closure, Reopening & Related Side Effects
 *
 * Phase 1: lifecycle authority is the state machine (not is_active / is_closed / status).
 */
class TermClosureService
{
    public function closeTerm(Term $term, ?string $reason): void
    {
        if (! ($term->state instanceof TermActive)) {
            throw ValidationException::withMessages([
                'state' => 'Only active terms can be closed.',
            ]);
        }

        DB::transaction(function () use ($term) {
            $term->state->transitionTo(TermClosedState::class);
            $term->forceFill(['closed_at' => now()])->save();
        });

        event(new TermClosed($term));

        $this->notifyTermClosed($term);

        activity()
            ->performedOn($term)
            ->causedBy(auth()->user())
            ->withProperties([
                'session_id'    => $term->academic_session_id,
                'session_name'  => $term->academicSession->name,
                'reason'        => $reason ?? null,
            ])
            ->log("Term '{$term->display_name}' was **closed**" . ($reason ? " (Reason: {$reason})" : ''));

        Log::info("Term closed: {$term->display_name} in {$term->academicSession->name}", [
            'term_id' => $term->id,
            'session_id' => $term->academic_session_id,
            'user_id' => auth()->id(),
            'closed_at' => now(),
        ]);
    }

    public function reopenTerm(Term $term, string $reason, string $newEndDate): void
    {
        if (! ($term->state instanceof TermClosedState)) {
            throw ValidationException::withMessages([
                'state' => 'This term is not closed and cannot be reopened.',
            ]);
        }

        $session = $term->academicSession;
        $newEnd = Carbon::parse($newEndDate)->startOfDay();

        if ($newEnd->gt($session->end_date)) {
            throw ValidationException::withMessages([
                'new_end_date' => "New end date cannot exceed the session end date ({$session->end_date->format('Y-m-d')}).",
            ]);
        }

        $nextOrdinal = $term->ordinal_number + 1;
        $nextTerm = Term::where('academic_session_id', $session->id)
            ->where('ordinal_number', $nextOrdinal)
            ->first();

        if ($nextTerm && $nextTerm->start_date && $newEnd->gte($nextTerm->start_date)) {
            throw ValidationException::withMessages([
                'new_end_date' => "New end date cannot be on or after the next term's start date ({$nextTerm->start_date->format('Y-m-d')}).",
            ]);
        }

        if ($newEnd->lt($term->start_date)) {
            throw ValidationException::withMessages([
                'new_end_date' => 'New end date must be on or after the term\'s original start date.',
            ]);
        }

        $lastClosed = Term::where('academic_session_id', $session->id)
            ->where('state', TermClosedState::$name)
            ->orderByDesc('closed_at')
            ->first();

        if (! $lastClosed || $lastClosed->id !== $term->id) {
            throw ValidationException::withMessages([
                'term' => 'Only the most recently closed term can be reopened.',
            ]);
        }

        // Phase 1: TermState has no Closed→Active transition; preserve legacy reopen via direct assignment.
        DB::transaction(function () use ($term, $newEnd) {
            $term->forceFill([
                'state' => TermActive::$name,
                'end_date' => $newEnd,
                'closed_at' => null,
            ])->save();
        });

        event(new TermReopened($term));

        $this->notifyTermReopened($term);

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

    protected function notifyTermClosed(Term $term): void
    {
        $notifiables = $this->getRelevantNotifiables($term);

        foreach ($notifiables as $user) {
            $user->notify(new TermClosedNotification($term));
        }
    }

    protected function notifyTermReopened(Term $term): void
    {
        $notifiables = $this->getRelevantNotifiables($term);

        foreach ($notifiables as $user) {
            $user->notify(new TermReopenedNotification($term));
        }
    }

    protected function getRelevantNotifiables(Term $term): \Illuminate\Support\Collection
    {
        return $term->school->users()
            ->whereIn('role', ['admin', 'principal', 'head-teacher'])
            ->get();
    }

    protected function lockTermAssessments(Term $term): void
    {
        // TODO: assessment module
    }

    protected function unlockTermAssessments(Term $term): void
    {
        // TODO: assessment module
    }
}
