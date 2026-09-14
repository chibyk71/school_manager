<?php

namespace App\Services;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Models\School;
use App\States\Academic\AcademicSession\Active as SessionActive;
use App\States\Academic\AcademicSession\Paused as SessionPaused;
use App\States\Academic\Term\Active as TermActive;
use App\Support\AcademicContext;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Authoritative application-facing academic context resolver (Phase 5).
 *
 * Current operational session = ACTIVE or PAUSED (at most one per school).
 * Current term = ACTIVE term belonging to that session (nullable).
 *
 * Lifecycle mutation remains in AcademicSessionLifecycleService / TermLifecycleService.
 * This service does not switch sessions or terms and does not expose writability policy.
 */
class AcademicSessionService
{
    private const CACHE_TTL_MINUTES = 15;

    /** Must match AcademicSessionLifecycleService / TermLifecycleService / AcademicCalendarService. */
    private const CACHE_KEY_SESSION = 'current_academic_session_';

    private const CACHE_KEY_TERM = 'current_academic_term_';

    /**
     * Current operational session for the current school (ACTIVE or PAUSED only).
     */
    public function currentSession(): ?AcademicSession
    {
        $school = GetSchoolModel();
        if (! $school) {
            return null;
        }

        $key = self::CACHE_KEY_SESSION.$school->id;

        return Cache::remember($key, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($school) {
            return AcademicSession::query()
                ->where('school_id', $school->id)
                ->whereIn('state', [SessionActive::$name, SessionPaused::$name])
                ->first();
        });
    }

    /**
     * ACTIVE term belonging to the current operational session, or null.
     */
    public function currentTerm(): ?Term
    {
        $school = GetSchoolModel();
        if (! $school) {
            return null;
        }

        $key = self::CACHE_KEY_TERM.$school->id;

        return Cache::remember($key, now()->addMinutes(self::CACHE_TTL_MINUTES), function () {
            $session = $this->currentSession();
            if (! $session) {
                return null;
            }

            return Term::query()
                ->where('academic_session_id', $session->id)
                ->where('state', TermActive::$name)
                ->first();
        });
    }

    /**
     * Full current academic context, or null when there is no current session.
     * Term is intentionally nullable.
     */
    public function currentContext(): ?AcademicContext
    {
        $school = GetSchoolModel();
        if (! $school) {
            return null;
        }

        $session = $this->currentSession();
        if (! $session) {
            return null;
        }

        return new AcademicContext(
            school: $school,
            session: $session,
            term: $this->currentTerm(),
            sessionState: $this->resolveSessionStateName($session),
        );
    }

    /**
     * @throws RuntimeException when no current operational session exists
     */
    public function requireCurrentSession(): AcademicSession
    {
        $session = $this->currentSession();
        if (! $session) {
            throw new RuntimeException('No current operational academic session for the current school.');
        }

        return $session;
    }

    /**
     * @throws RuntimeException when no current active term exists
     */
    public function requireCurrentTerm(): Term
    {
        $term = $this->currentTerm();
        if (! $term) {
            throw new RuntimeException('No current active academic term for the current school.');
        }

        return $term;
    }

    /**
     * Requires a current session; term may still be null.
     *
     * @throws RuntimeException when no current operational session exists
     */
    public function requireCurrentContext(): AcademicContext
    {
        $context = $this->currentContext();
        if (! $context) {
            throw new RuntimeException('No current operational academic session for the current school.');
        }

        return $context;
    }

    /**
     * Authoritative session state name for the current operational session, or null.
     */
    public function sessionState(): ?string
    {
        $session = $this->currentSession();
        if (! $session) {
            return null;
        }

        return $this->resolveSessionStateName($session);
    }

    public function isSessionActive(): bool
    {
        return $this->sessionState() === SessionActive::$name;
    }

    public function isSessionPaused(): bool
    {
        return $this->sessionState() === SessionPaused::$name;
    }

    /**
     * Forget current-session / current-term cache for a school.
     * Lifecycle services already call their own invalidation; this is available for tests and edge cases.
     */
    public function invalidateCaches(string $schoolId): void
    {
        Cache::forget(self::CACHE_KEY_SESSION.$schoolId);
        Cache::forget(self::CACHE_KEY_TERM.$schoolId);
    }

    private function resolveSessionStateName(AcademicSession $session): string
    {
        $state = $session->state;
        if (is_object($state) && method_exists($state, 'getValue')) {
            return (string) $state->getValue();
        }

        return (string) $state;
    }
}
