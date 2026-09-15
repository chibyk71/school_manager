<?php

namespace App\Facades;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Support\AcademicContext;
use Illuminate\Support\Facades\Facade;

/**
 * Application-facing Academic context API (Phase 5).
 *
 * @method static AcademicSession|null currentSession()
 * @method static Term|null currentTerm()
 * @method static AcademicContext|null currentContext()
 * @method static AcademicSession requireCurrentSession()
 * @method static Term requireCurrentTerm()
 * @method static AcademicContext requireCurrentContext()
 * @method static string|null sessionState()
 * @method static bool isSessionActive()
 * @method static bool isSessionPaused()
 * @method static array sessionsForSchool(\App\Models\School|string $school)
 * @method static bool sessionBelongsToSchool(\App\Models\School|string $school, string $sessionId)
 * @method static void invalidateCaches(string $schoolId)
 *
 * @see \App\Services\AcademicSessionService
 */
class Academic extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'academicContext';
    }
}
