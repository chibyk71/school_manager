<?php

namespace App\Services\Academic;

use App\Contracts\Academic\TermOperationalDataBoundary;
use App\Events\Academic\TermActivated;
use App\Events\Academic\TermClosed;
use App\Events\Academic\TermCreated;
use App\Events\Academic\TermDeleted;
use App\Events\Academic\TermRestored;
use App\Events\Academic\TermUpdated;
use App\Models\Academic\AcademicSession;
use App\Services\Academic\AcademicPeriodLock;
use App\Models\Academic\Term;
use App\States\Academic\AcademicSession\Active as SessionActive;
use App\States\Academic\Term\Active as TermActive;
use App\States\Academic\Term\Closed as TermClosedState;
use App\States\Academic\Term\Planned as TermPlanned;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\ModelStates\Exceptions\TransitionNotAllowed;

/**
 * Authoritative domain service for Term lifecycle (Phase 3).
 *
 * Controllers remain thin: authorize → validate → invoke these operations.
 * Soft-deleted terms park ordinals at ORDINAL_TOMBSTONE_BASE+ so UNIQUE(session, ordinal) holds.
 *
 * Phase 4: lockSessionTerms acquires AcademicPeriodLock::lockSchool first so Term
 * date/delete mutations serialize with Session mutations and dependency registration.
 */
class TermLifecycleService
{
    private const CACHE_KEY_TERM = 'current_academic_term_';

    /** Soft-deleted terms park ordinals at or above this base so live terms keep unique 1..N. */
    public const ORDINAL_TOMBSTONE_BASE = 1_000_000;

    public function __construct(
        protected TermOperationalDataBoundary $operationalData
    ) {
    }

    // FULL IMPLEMENTATION: this push is incomplete if methods below are missing.
    // Canonical full file: artifacts/academic-phase4/TermLifecycleService.php

    protected function lockSessionTerms(string $sessionId): void
    {
        $session = AcademicSession::query()->whereKey($sessionId)->lockForUpdate()->first();
        if ($session !== null) {
            // Same school lock as Session lifecycle and dependency registration.
            AcademicPeriodLock::lockSchool((string) $session->school_id);
        }
        Term::query()->where('academic_session_id', $sessionId)->lockForUpdate()->get();
    }

    protected function invalidateCaches(string $schoolId): void
    {
        Cache::forget(self::CACHE_KEY_TERM . $schoolId);
        Cache::forget('current_academic_session_' . $schoolId);
    }
}
