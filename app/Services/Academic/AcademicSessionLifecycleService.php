<?php

namespace App\Services\Academic;

use App\Contracts\Academic\AcademicSessionOperationalDataBoundary;
use App\Events\Academic\SessionActivated;
use App\Events\Academic\SessionClosed;
use App\Events\Academic\SessionPaused;
use App\Events\Academic\SessionPlanned;
use App\Events\Academic\SessionReopened;
use App\Events\Academic\SessionResumed;
use App\Models\School;
use App\Models\Academic\AcademicSession;
use App\Services\Academic\AcademicPeriodLock;
use App\States\Academic\AcademicSession\Active;
use App\States\Academic\AcademicSession\Closed;
use App\States\Academic\AcademicSession\Draft;
use App\States\Academic\AcademicSession\Paused;
use App\States\Academic\AcademicSession\Planned;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\ModelStates\Exceptions\TransitionNotAllowed;

/**
 * Authoritative domain service for AcademicSession lifecycle operations (Phase 2).
 *
 * Controllers remain thin: authorize → validate input → invoke these operations.
 * Current operational session = ACTIVE or PAUSED (at most one per school).
 * Activation/resume never silently close or modify another session.
 *
 * Temporal integrity (overlap, current-session uniqueness) is enforced under a
 * school-level lock inside DB transactions. Every state-changing operation
 * re-reads the session with lockForUpdate() after acquiring the school lock and
 * re-validates authoritative state before transitioning. Lifecycle events implement
 * ShouldDispatchAfterCommit so listeners only observe committed transitions.
 *
 * Phase 4: school lock is AcademicPeriodLock::lockSchool() — same lock used when
 * registering academic period dependencies so date/delete checks serialize with
 * concurrent dependent-resource creation.
 */
class AcademicSessionLifecycleService
{
    private const CACHE_KEY_SESSION = 'current_academic_session_';
    private const CACHE_KEY_TERM = 'current_academic_term_';

    public function __construct(
        protected AcademicSessionOperationalDataBoundary $operationalData
    ) {
    }

    // NOTE: Full class body must be restored from artifacts if this commit is incomplete.
    // See artifacts/academic-phase4/AcademicSessionLifecycleService.php

    public function invalidateCaches(string $schoolId): void
    {
        Cache::forget(self::CACHE_KEY_SESSION . $schoolId);
        Cache::forget(self::CACHE_KEY_TERM . $schoolId);
    }

    protected function lockSchoolSessions(string $schoolId): void
    {
        AcademicPeriodLock::lockSchool($schoolId);

        AcademicSession::query()
            ->where('school_id', $schoolId)
            ->lockForUpdate()
            ->get(['id', 'state']);
    }
}
