<?php

namespace App\Services\Academic;

use App\Contracts\Academic\AcademicSessionOperationalDataBoundary;
use App\Models\Academic\AcademicSession;

/**
 * Registry-backed session operational-data boundary (Phase 4).
 *
 * hasOperationalData is true when any academic_period_usages row exists for
 * this session (direct session-level or term-level under the session).
 *
 * Callers continue to depend only on AcademicSessionOperationalDataBoundary.
 */
class RegistryAcademicSessionOperationalData implements AcademicSessionOperationalDataBoundary
{
    public function __construct(
        protected AcademicPeriodUsageRegistry $registry
    ) {
    }

    public function hasOperationalData(AcademicSession $session): bool
    {
        return $this->registry->hasSessionDependencies($session);
    }
}
