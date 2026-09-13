<?php

namespace App\Services\Academic;

use App\Contracts\Academic\TermOperationalDataBoundary;
use App\Models\Academic\Term;

/**
 * Phase 3 placeholder: no operational data is reported for any term.
 *
 * Phase 4 MUST replace this implementation (or the binding) with a
 * registry-backed boundary that queries academic_period_usages.
 * Callers must continue to depend only on the TermOperationalDataBoundary interface.
 */
class NullTermOperationalData implements TermOperationalDataBoundary
{
    public function hasOperationalData(Term $term): bool
    {
        // Intentionally always false in Phase 3.
        // Phase 4 replaces this with the academic usage registry.
        return false;
    }
}
