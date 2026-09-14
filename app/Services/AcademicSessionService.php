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
 * Authoritative application-facing academic context and session query helpers.
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

    /** Must match AcademicSessionLifecycleService / TermLifecycleService cache keys. */
    private const CACHE_KEY_SESSION = 'current_academic_session_';

    private const CACHE_KEY_TERM = 'current_academic_term_';

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

    public function requireCurrentSession(): AcademicSession
    {
        $session = $this->currentSession();
        if (! $session) {
            throw new RuntimeException('No current operational academic session for the current school.');
        }

        return $session;
    }

    public function requireCurrentTerm(): Term
    {
        $term = $this->currentTerm();
        if (! $term) {
            throw new RuntimeException('No current active academic term for the current school.');
        }

        return $term;
    }

    public function requireCurrentContext(): AcademicContext
    {
        $context = $this->currentContext();
        if (! $context) {
            throw new RuntimeException('No current operational academic session for the current school.');
        }

        return $context;
    }

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
     * @return list<array{id: string, name: string, state: string}>
     */
    public function sessionsForSchool(School|string $school): array
    {
        $schoolId = is_object($school) ? $school->id : $school;

        return AcademicSession::query()
            ->where('school_id', $schoolId)
            ->orderByRaw('CASE WHEN state IN (?, ?) THEN 0 ELSE 1 END', [
                SessionActive::$name,
                SessionPaused::$name,
            ])
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'state'])
            ->map(function (AcademicSession $row) {
                return [
                    'id' => (string) $row->id,
                    'name' => (string) $row->name,
                    'state' => $this->resolveSessionStateName($row),
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
