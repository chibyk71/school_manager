<?php

namespace App\Services;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Models\School;
use App\States\Academic\AcademicSession\Active as AcademicSessionActive;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Academic calendar helpers (date validation, session membership, listings).
 *
 * Phase 5: authoritative current-session / current-term resolution lives in
 * AcademicSessionService (Academic facade). This class no longer owns that
 * responsibility; currentSession()/currentTerm() delegate for backward
 * compatibility until remaining call sites migrate.
 *
 * Phase 6 will introduce the Academic Calendar domain for calendar events.
 * Do not add event/calendar features here.
 */
class AcademicCalendarService
{
    /**
     * @deprecated Use Academic::currentSession() / AcademicSessionService::currentSession()
     */
    public function currentSession(): ?AcademicSession
    {
        return app(AcademicSessionService::class)->currentSession();
    }

    /**
     * @deprecated Use Academic::currentTerm() / AcademicSessionService::currentTerm()
     */
    public function currentTerm(): ?Term
    {
        return app(AcademicSessionService::class)->currentTerm();
    }

    /**
     * @return list<array{id: string, name: string, state: string}>
     */
    public function sessionsForSchool(School|string $school): array
    {
        $schoolId = is_object($school) ? $school->id : $school;

        return AcademicSession::query()
            ->where('school_id', $schoolId)
            ->orderByRaw('CASE WHEN state IN (?, ?) THEN 0 ELSE 1 END', [
                AcademicSessionActive::$name,
                \App\States\Academic\AcademicSession\Paused::$name,
            ])
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
        $term = app(AcademicSessionService::class)->currentTerm();
        if (! $term) {
            return false;
        }

        $date = Carbon::parse($date);

        return $date->between($term->start_date, $term->end_date);
    }
}
