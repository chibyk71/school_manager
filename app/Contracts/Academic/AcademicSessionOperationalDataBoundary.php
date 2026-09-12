<?php

namespace App\Contracts\Academic;

use App\Models\Academic\AcademicSession;

/**
 * Boundary for determining whether an academic session has operational data.
 *
 * Phase 2 (current): placeholder implementation always returns false.
 * Phase 4 will replace this with the academic_period_usages registry.
 *
 * Callers MUST depend on this abstraction, never on placeholder behaviour
 * or table-by-table discovery. Do not duplicate operational-data detection.
 */
interface AcademicSessionOperationalDataBoundary
{
    /**
     * Whether the given session has any operational (usage-tracked) data.
     *
     * @return bool true when operational records exist for this session
     */
    public function hasOperationalData(AcademicSession $session): bool;
}
