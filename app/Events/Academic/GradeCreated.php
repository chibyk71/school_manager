<?php

namespace App\Events\Academic;

use App\Models\Academic\Grade;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * GradeCreated Event
 *
 * Fired immediately after a new Grade is successfully created.
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Decouples grade creation from downstream side-effects (e.g. cache invalidation, notifications, result recalculation)
 * • Allows listeners to run asynchronously (via queue) without blocking the HTTP request
 * • Passes the full Grade instance → listeners have access to all attributes & relations
 * • Serializable → safe for queued jobs (e.g. recalculate GPAs for affected students)
 * • Namespaced under Academic → clear module boundary
 *
 * How it fits into the Grades Module:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Dispatched from GradeService::create() after successful DB insert
 * • Listeners can:
 *   - Invalidate any cached grading scales
 *   - Log detailed audit info (beyond Spatie Activitylog)
 *   - Trigger background jobs (e.g. update default grades for new sections)
 *   - Send admin notifications (if grade scale changes are restricted)
 * • Works with EventServiceProvider auto-discovery or manual mapping
 *
 * Usage Example (in GradeService):
 *   event(new GradeCreated($grade));
 * or
 *   GradeCreated::dispatch($grade);
 */
class GradeCreated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * The newly created grade instance.
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
