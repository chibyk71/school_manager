<?php

namespace App\Services;

use App\Events\Academic\SessionActivated;
use App\Events\Academic\SessionClosed;
use App\Events\Academic\TermActivated;
use App\Events\Academic\TermClosed;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Models\School;
use App\States\Academic\AcademicSession\Active as AcademicSessionActive;
use App\States\Academic\AcademicSession\Closed as AcademicSessionClosed;
use App\States\Academic\Term\Active as TermActive;
use App\States\Academic\Term\Closed as TermClosedState;
use App\States\Academic\Term\Planned as TermPlanned;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * AcademicCalendarService – Core Business Logic for Academic Sessions & Terms
 *
 * Phase 2: current session is ACTIVE or PAUSED. Lifecycle mutation delegates to
 * AcademicSessionLifecycleService (no silent switch of another session).
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
            // Phase 2: current operational session is ACTIVE or PAUSED
            return AcademicSession::where('school_id', $school->id)
                ->whereIn('state', [
                    AcademicSessionActive::$name,
                    \App\States\Academic\AcademicSession\Paused::$name,
                ])
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
     * @return list<array{id: string, name: string, state: string}>
     */
    public function sessionsForSchool(School|string $school): array
    {
        $schoolId = is_object($school) ? $school->id : $school;

        return AcademicSession::query()
            ->where('school_id', $schoolId)
            ->orderByRaw('CASE WHEN state IN (?, ?) THEN 0 ELSE 1 END', [AcademicSessionActive::$name, \App\States\Academic\AcademicSession\Paused::$name])
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'state'])
            ->map(function (AcademicSession $row) {
                $state = $row->state;
                $value = is_object($state) && method_exists($state, 'getValue')
                    ? $state->getValue()
                    : (string) $state;

                return [
                    'id' => (string) $row->id,
                    'name' => (string) $row->name,
                    'state' => $value,
                ];
            })
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

    /**
     * @deprecated Use AcademicSessionLifecycleService::activate() instead.
     * Kept as a thin delegator; no silent switch of another session.
     */
    public function activateSession(AcademicSession $session): void
    {
        app(\App\Services\Academic\AcademicSessionLifecycleService::class)->activate($session);
    }

    /**
     * @deprecated Use AcademicSessionLifecycleService::close() instead.
     */
    public function closeSession(AcademicSession $session): void
    {
        app(\App\Services\Academic\AcademicSessionLifecycleService::class)->close($session);
    }

    public function activateTerm(Term $term): void
    {
        $session = $term->academicSession;
        if (! ($session->state instanceof AcademicSessionActive)) {
            throw ValidationException::withMessages(['session' => 'Parent session must be active first.']);
        }

        $this->validateTermDates($term, $session);

        DB::transaction(function () use ($term) {
            Term::where('academic_session_id', $term->academic_session_id)
                ->where('state', TermActive::$name)
                ->where('id', '!=', $term->id)
                ->update(['state' => TermClosedState::$name]);

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
            $term->state->transitionTo(TermClosedState::class);
            $term->forceFill(['closed_at' => now()])->save();

            Cache::forget(self::CACHE_KEY_TERM . $term->school_id);
        });

        event(new TermClosed($term));
    }

    public function reopenTerm(Term $term): void
    {
        if (! ($term->state instanceof TermClosedState)) {
            throw ValidationException::withMessages(['state' => 'Term is not closed.']);
        }

        $session = $term->academicSession;

        $lastClosed = Term::where('academic_session_id', $session->id)
            ->where('state', TermClosedState::$name)
            ->orderByDesc('closed_at')
            ->first();

        if ($lastClosed?->id !== $term->id) {
            throw ValidationException::withMessages(['term' => 'Only the most recently closed term can be reopened.']);
        }

        $nextTerm = Term::where('academic_session_id', $session->id)
            ->where('ordinal_number', $term->ordinal_number + 1)
            ->first();

        if ($nextTerm && (($nextTerm->state instanceof TermActive) || ($nextTerm->state instanceof TermClosedState))) {
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
