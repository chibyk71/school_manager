<?php

namespace App\Services\Academic;

use App\Contracts\Academic\AcademicSessionOperationalDataBoundary;
use App\Models\Academic\AcademicSession;

/**
 * Phase 2 placeholder: no operational data is reported for any session.
 *
 * Phase 4 MUST replace this implementation (or the binding) with a
 * registry-backed AcademicSessionUsageBoundary that queries
 * academic_period_usages. Callers must continue to depend only on the
 * AcademicSessionOperationalDataBoundary interface.
 */
class NullAcademicSessionOperationalData implements AcademicSessionOperationalDataBoundary
{
    public function hasOperationalData(AcademicSession $session): bool
    {
        // Intentionally always false in Phase 2.
        // Phase 4 replaces this with the academic usage registry.
        return false;
    }
}
