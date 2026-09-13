<?php

namespace App\Contracts\Academic;

use App\Models\Academic\Term;

/**
 * Boundary for determining whether a Term has operational data.
 *
 * Phase 3: placeholder implementation always returns false.
 * Phase 4 will replace this with the academic_period_usages registry
 * (term-scoped queries). Callers MUST depend only on this interface.
 */
interface TermOperationalDataBoundary
{
    /**
     * Whether the given term has any operational (usage-tracked) data.
     *
     * @return bool true when operational records exist for this term
     */
    public function hasOperationalData(Term $term): bool;
}
