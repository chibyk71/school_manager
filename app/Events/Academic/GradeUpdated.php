<?php

namespace App\Events\Academic;

use App\Models\Academic\Grade;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * GradeUpdated Event
 *
 * Fired after an existing Grade is successfully updated.
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Critical for reacting to grading scale changes (most impactful event)
 * • Enables retroactive recalculation of student results/exam grades that used old ranges
 * • Passes original attributes (via model) so listeners can compare old vs new
 * • Queue-safe → heavy operations (recalculation) can be deferred
 * • Supports partial updates (only dirty fields are logged elsewhere)
 *
 * How it fits into the Grades Module:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Dispatched from GradeService::update() after $grade->update()
 * • Primary trigger for:
 *   - Queueing a job to re-grade affected ExamResults
 *   - Invalidating frontend caches (if using Redis or similar)
 *   - Logging detailed change summary (if Spatie needs supplementation)
 *   - Notifying admins/teachers of scale modifications
 * • Most important event for data consistency in production
 *
 * Usage Example:
 *   event(new GradeUpdated($grade));
 */
class GradeUpdated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * The updated grade instance (after changes applied).
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
