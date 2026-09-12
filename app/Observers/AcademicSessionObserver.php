<?php

namespace App\Observers;

use App\Models\Academic\AcademicSession;

/**
 * AcademicSessionObserver
 *
 * Phase 2: automatic term creation on session create has been removed.
 * Term creation and lifecycle belong to Phase 3. A DRAFT session may be
 * incomplete and must not fabricate First/Second/Third Term records.
 */
class AcademicSessionObserver
{
    public function created(AcademicSession $academicSession): void
    {
        // Intentionally empty — no automatic term creation (Phase 2).
    }

    public function updated(AcademicSession $academicSession): void
    {
        //
    }

    public function deleted(AcademicSession $academicSession): void
    {
        //
    }

    public function restored(AcademicSession $academicSession): void
    {
        //
    }

    public function forceDeleted(AcademicSession $academicSession): void
    {
        //
    }
}
