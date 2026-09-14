<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Models\Academic\AcademicSession|null currentSession()
 * @method static \App\Models\Academic\Term|null currentTerm()
 * @method static \App\Support\AcademicContext|null currentContext()
 * @method static \App\Models\Academic\AcademicSession requireCurrentSession()
 * @method static \App\Models\Academic\Term requireCurrentTerm()
 * @method static \App\Support\AcademicContext requireCurrentContext()
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
