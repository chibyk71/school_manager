<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\LifecycleOperationalService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Phase 7 operational views: Needs Attention, Upcoming Deadlines, Recently Completed.
 * All queries are school-scoped via LifecycleOperationalService.
 */
class LifecycleOperationsController extends Controller
{
    public function __construct(
        protected LifecycleOperationalService $ops
    ) {}

    public function needsAttention(Request $request)
    {
        $this->authorizeLifecycleView();

        $school = GetSchoolModel();
        $data = $this->ops->needsAttention($school, $request->integer('limit', 50));

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return Inertia::render('Student/Lifecycle/NeedsAttention', [
            'items' => $data['items'],
            'total' => $data['total'],
        ]);
    }

    public function upcomingDeadlines(Request $request)
    {
        $this->authorizeLifecycleView();

        $school = GetSchoolModel();
        $days = max(1, min(90, $request->integer('within_days', 14)));
        $data = $this->ops->upcomingDeadlines($school, $days, $request->integer('limit', 50));

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return Inertia::render('Student/Lifecycle/UpcomingDeadlines', [
            'items' => $data['items'],
            'total' => $data['total'],
            'within_days' => $days,
        ]);
    }

    public function recentlyCompleted(Request $request)
    {
        $this->authorizeLifecycleView();

        $school = GetSchoolModel();
        $days = max(1, min(90, $request->integer('within_days', 14)));
        $data = $this->ops->recentlyCompleted($school, $days, $request->integer('limit', 50));

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return Inertia::render('Student/Lifecycle/RecentlyCompleted', [
            'items' => $data['items'],
            'total' => $data['total'],
            'within_days' => $days,
        ]);
    }

    public function dashboardSummary(Request $request)
    {
        $this->authorizeLifecycleView();

        $school = GetSchoolModel();

        return response()->json([
            'counts' => $this->ops->dashboardCounts($school),
            'needs_attention' => $this->ops->needsAttention($school, 10),
            'upcoming_deadlines' => $this->ops->upcomingDeadlines($school, 7, 10),
            'recently_completed' => $this->ops->recentlyCompleted($school, 7, 10),
        ]);
    }

    protected function authorizeLifecycleView(): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        // Accept any of the lifecycle view permissions already seeded in prior phases.
        $allowed = $user->can('viewAny', \App\Models\Student\Enrollment::class)
            || $user->can('viewAny', \App\Models\Student\Admission::class)
            || $user->can('viewAny', \App\Models\Student\StudentApplication::class);

        if (! $allowed) {
            abort(403);
        }
    }
}
