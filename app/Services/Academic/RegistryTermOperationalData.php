<?php

namespace App\Services\Academic;

use App\Contracts\Academic\TermOperationalDataBoundary;
use App\Models\Academic\Term;

/**
 * Registry-backed term operational-data boundary (Phase 4).
 *
 * hasOperationalData is true when any academic_period_usages row is registered
 * against this term specifically (not sibling terms or session-only rows).
 *
 * Callers continue to depend only on TermOperationalDataBoundary.
 */
class RegistryTermOperationalData implements TermOperationalDataBoundary
{
    public function __construct(
        protected AcademicPeriodUsageRegistry $registry
    ) {
    }

    public function hasOperationalData(Term $term): bool
    {
        return $this->registry->hasTermDependencies($term);
    }
}
