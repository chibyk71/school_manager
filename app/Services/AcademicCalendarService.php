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
 * Phase 3: Term activate/close delegate to TermLifecycleService; reopen removed.
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

    /**
     * @deprecated Use TermLifecycleService::activate() instead.
     */
    public function activateTerm(Term $term): void
    {
        app(\App\Services\Academic\TermLifecycleService::class)->activate($term);
    }

    /**
     * @deprecated Use TermLifecycleService::close() instead.
     */
    public function closeTerm(Term $term): void
    {
        app(\App\Services\Academic\TermLifecycleService::class)->close($term);
    }

    /**
     * @deprecated Phase 3 removes reopen. CLOSED → ACTIVE is not a valid transition.
     * @throws ValidationException always
     */
    public function reopenTerm(Term $term): void
    {
        throw ValidationException::withMessages([
            'state' => 'Term reopen is not supported. CLOSED terms cannot become ACTIVE.',
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
