<?php

namespace App\Events\Academic;

use App\Models\Academic\Grade;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * GradeDeleted Event
 *
 * Fired after a Grade is soft-deleted (or force-deleted if configured).
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Allows cleanup or warning actions when a grade is removed
 * • Prevents orphaned references in results (via listener checks)
 * • Handles both soft & force delete scenarios
 * • Queueable for expensive operations (e.g. nullify grade_id in results)
 *
 * How it fits into the Grades Module:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Dispatched from GradeService::delete() after $grade->delete()
 * • Listeners can:
 *   - Set grade_id = null in affected ExamResults (with warning)
 *   - Log high-severity audit entry
 *   - Notify admins if grade was in use
 *   - Prevent deletion if isUsed() (better handled in service, but event as fallback)
 *
 * Usage Example:
 *   event(new GradeDeleted($grade));
 */
class GradeDeleted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * The deleted grade instance.
     *
     * @var Grade
     */
    public Grade $grade;

    /**
     * Create a new event instance.
     */
    public function __construct(Grade $grade)
    {
        $this->grade = $grade;
    }
}
