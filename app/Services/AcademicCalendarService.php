<?php

namespace App\Services;

use App\Events\Academic\SessionActivated;
use App\Events\Academic\SessionClosed;
use App\Events\Academic\TermActivated;
use App\Events\Academic\TermClosed;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Models\School;
use App\States\Academic\AcademicSession\Active as SessionActive;
use App\States\Academic\AcademicSession\Closed as SessionClosed;
use App\States\Academic\Term\Active as TermActive;
use App\States\Academic\Term\Closed as TermClosed;
use App\States\Academic\Term\Planned as TermPlanned;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * AcademicCalendarService – Core Business Logic for Academic Sessions & Terms
 *
 * Phase 1: lifecycle authority is the state machine (not is_current / is_active / status).
 */
class AcademicCalendarService
{
    private const CACHE_TTL_MINUTES = 15;

    private const CACHE_KEY_SESSION = 'current_academic_session_';
    private const CACHE_KEY_TERM    = 'current_academic_term_';

    public function currentSession(): ?AcademicSession
    {
        $school = GetSchoolModel();
        if (! $school) {
            return null;
        }

        $key = self::CACHE_KEY_SESSION . $school->id;

        return Cache::remember($key, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($school) {
            return AcademicSession::where('school_id', $school->id)
                ->where('state', SessionActive::$name)
                ->first();
        });
    }

    public function currentTerm(): ?Term
    {
        $school = GetSchoolModel();
        if (! $school) {
            return null;
        }

        $key = self::CACHE_KEY_TERM . $school->id;

        return Cache::remember($key, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($school) {
            $session = $this->currentSession();
            if (! $session) {
                return null;
            }

            return Term::where('academic_session_id', $session->id)
                ->where('state', TermActive::$name)
                ->first();
        });
    }

    /**
     * List academic sessions for a school (filter / report options).
     *
     * Ordered: ACTIVE first, then most recently created.
     * Returns authoritative state (not legacy is_current).
     *
     * @return list<array{id: string, name: string, state: string}>
     */
    public function sessionsForSchool(School|string $school): array
    {
        $schoolId = is_object($school) ? $school->id : $school;

        return AcademicSession::query()
            ->where('school_id', $schoolId)
            ->orderByRaw('CASE WHEN state = ? THEN 0 ELSE 1 END', [SessionActive::$name])
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'state'])
            ->map(fn (AcademicSession $row) => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'state' => $row->state instanceof SessionActive
                    || (is_object($row->state) && method_exists($row->state, 'getValue'))
                    ? (is_object($row->state) && method_exists($row->state, 'getValue') ? $row->state->getValue() : (string) $row->state)
                    : (string) $row->state,
            ])
            ->all();
    }

    public function sessionBelongsToSchool(School|string $school, string $sessionId): bool
    {
        $schoolId = is_object($school) ? $school->id : $school;

        return AcademicSession::query()
            ->whereKey($sessionId)
            ->where('school_id', $schoolId)
            ->exists();
    }

    public function activateSession(AcademicSession $session): void
    {
        $school = GetSchoolModel();
        if ($session->school_id !== $school->id) {
            throw ValidationException::withMessages(['session' => 'Session does not belong to current school.']);
        }

        if ($session->state instanceof SessionActive) {
            return;
        }

        DB::transaction(function () use ($session) {
            // Legacy parity: ensure at most one ACTIVE session per school
            AcademicSession::where('school_id', $session->school_id)
                ->where('state', SessionActive::$name)
                ->where('id', '!=', $session->id)
                ->update(['state' => SessionClosed::$name]);

            $session->state->transitionTo(SessionActive::class);
            $session->forceFill(['activated_at' => now()])->save();

            Cache::forget(self::CACHE_KEY_SESSION . $session->school_id);
        });

        event(new SessionActivated($session));
    }

    public function closeSession(AcademicSession $session): void
    {
        if (! ($session->state instanceof SessionActive)) {
            throw ValidationException::withMessages(['state' => 'Only active sessions can be closed.']);
        }

        DB::transaction(function () use ($session) {
            $session->state->transitionTo(SessionClosed::class);
            $session->forceFill(['closed_at' => now()])->save();

            Cache::forget(self::CACHE_KEY_SESSION . $session->school_id);
        });

        event(new SessionClosed($session));
    }

    public function activateTerm(Term $term): void
    {
        $session = $term->academicSession;
        if (! ($session->state instanceof SessionActive)) {
            throw ValidationException::withMessages(['session' => 'Parent session must be active first.']);
        }

        $this->validateTermDates($term, $session);

        DB::transaction(function () use ($term) {
            Term::where('academic_session_id', $term->academic_session_id)
                ->where('state', TermActive::$name)
                ->where('id', '!=', $term->id)
                ->update(['state' => TermClosed::$name]);

            if ($term->state instanceof TermPlanned) {
                $term->state->transitionTo(TermActive::class);
            } elseif (! ($term->state instanceof TermActive)) {
                $term->forceFill(['state' => TermActive::$name])->save();
            } else {
                return;
            }

            Cache::forget(self::CACHE_KEY_TERM . $term->school_id);
        });

        event(new TermActivated($term));
    }

    public function closeTerm(Term $term): void
    {
        if (! ($term->state instanceof TermActive)) {
            throw ValidationException::withMessages(['state' => 'Only active terms can be closed.']);
        }

        DB::transaction(function () use ($term) {
            $term->state->transitionTo(TermClosed::class);
            $term->forceFill(['closed_at' => now()])->save();

            Cache::forget(self::CACHE_KEY_TERM . $term->school_id);
        });

        event(new TermClosed($term));
    }

    /**
     * Phase 1 compatibility reopen. Term has no Closed→Active transition;
     * Phase 3 owns the full reopen workflow. Uses direct state assignment.
     */
    public function reopenTerm(Term $term): void
    {
        if (! ($term->state instanceof TermClosed)) {
            throw ValidationException::withMessages(['state' => 'Term is not closed.']);
        }

        $session = $term->academicSession;

        $lastClosed = Term::where('academic_session_id', $session->id)
            ->where('state', TermClosed::$name)
            ->orderByDesc('closed_at')
            ->first();

        if ($lastClosed?->id !== $term->id) {
            throw ValidationException::withMessages(['term' => 'Only the most recently closed term can be reopened.']);
        }

        $nextTerm = Term::where('academic_session_id', $session->id)
            ->where('ordinal_number', $term->ordinal_number + 1)
            ->first();

        if ($nextTerm && (($nextTerm->state instanceof TermActive) || ($nextTerm->state instanceof TermClosed))) {
            throw ValidationException::withMessages(['next_term' => 'Cannot reopen: next term has already started or closed.']);
        }

        DB::transaction(function () use ($term) {
            $term->forceFill([
                'state' => TermActive::$name,
                'closed_at' => null,
            ])->save();

            Cache::forget(self::CACHE_KEY_TERM . $term->school_id);
        });

        Log::info("Term {$term->name} reopened in session {$term->academicSession->name}", [
            'term_id' => $term->id,
            'user'    => auth()->id(),
        ]);
    }

    public function validateTermDates(Term $term, AcademicSession $session): void
    {
        $errors = [];

        if ($term->start_date && $term->start_date->lt($session->start_date)) {
            $errors['start_date'] = 'Term start date cannot be before session start date.';
        }

        if ($term->end_date && $term->end_date->gt($session->end_date)) {
            $errors['end_date'] = 'Term end date cannot be after session end date.';
        }

        if ($term->start_date && $term->end_date && $term->start_date->gt($term->end_date)) {
            $errors['dates'] = 'Term start date must be before or equal to end date.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function isDateInCurrentTerm(Carbon|string $date): bool
    {
        $term = $this->currentTerm();
        if (! $term) {
            return false;
        }

        $date = Carbon::parse($date);

        return $date->between($term->start_date, $term->end_date);
    }
}
