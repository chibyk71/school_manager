<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\StudentApplication;
use App\Services\AcademicCalendarService;
use App\Services\Student\LifecycleOperationalService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LifecycleOperationsController extends Controller
{
    public function __construct(
        protected LifecycleOperationalService $ops
    ) {}

    public function needsAttention(Request $request)
    {
        $this->authorizeOperations();

        $school = GetSchoolModel();
        $categories = $this->authorizedCategories();
        $limit = $request->integer('limit', 50);
        $data = $this->ops->needsAttention($school, $limit, $categories);

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return Inertia::render('Student/Lifecycle/NeedsAttention', [
            'items' => $data['items'],
            'total' => $data['total'] ?? count($data['items']),
            'returned_count' => $data['returned_count'] ?? count($data['items']),
        ]);
    }

    public function upcomingDeadlines(Request $request)
    {
        $this->authorizeAny(['admissions.view', 'enrollments.view']);

        $school = GetSchoolModel();
        $days = max(1, min(90, $request->integer('within_days', 14)));
        $categories = array_values(array_intersect(
            $this->authorizedCategories(),
            ['admissions', 'enrollments']
        ));
        $data = $this->ops->upcomingDeadlines($school, $days, $request->integer('limit', 50), $categories);

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return Inertia::render('Student/Lifecycle/UpcomingDeadlines', [
            'items' => $data['items'],
            'total' => $data['total'] ?? count($data['items']),
            'returned_count' => $data['returned_count'] ?? count($data['items']),
            'within_days' => $days,
        ]);
    }

    public function recentlyCompleted(Request $request)
    {
        $this->authorizeOperations();

        $school = GetSchoolModel();
        $days = max(1, min(90, $request->integer('within_days', 14)));
        $categories = $this->authorizedCategories();
        $data = $this->ops->recentlyCompleted($school, $days, $request->integer('limit', 50), $categories);

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return Inertia::render('Student/Lifecycle/RecentlyCompleted', [
            'items' => $data['items'],
            'total' => $data['total'] ?? count($data['items']),
            'returned_count' => $data['returned_count'] ?? count($data['items']),
            'within_days' => $days,
        ]);
    }

    public function dashboardSummary(Request $request)
    {
        $this->authorizeOperations();

        $school = GetSchoolModel();
        $categories = $this->authorizedCategories();
        $session = app(AcademicCalendarService::class)->currentSession();
        $counts = $this->ops->dashboardCounts($school, $session);

        if (! in_array('applications', $categories, true)) {
            unset($counts['applications_awaiting_review']);
        }
        if (! in_array('admissions', $categories, true)) {
            unset(
                $counts['offers_awaiting_acceptance'],
                $counts['offers_expiring_soon'],
                $counts['accepted_awaiting_registration']
            );
        }
        if (! in_array('enrollments', $categories, true)) {
            unset(
                $counts['enrollments_in_progress'],
                $counts['ready_for_finalization'],
                $counts['awaiting_placement']
            );
        }

        return response()->json([
            'counts' => $counts,
            'needs_attention' => $this->ops->needsAttention($school, 10, $categories)['items'] ?? [],
            'upcoming_deadlines' => $this->ops->upcomingDeadlines(
                $school,
                7,
                10,
                array_values(array_intersect($categories, ['admissions', 'enrollments']))
            )['items'] ?? [],
            'recently_completed' => $this->ops->recentlyCompleted($school, 7, 10, $categories)['items'] ?? [],
        ]);
    }

    /** @return list<string> */
    protected function authorizedCategories(): array
    {
        $categories = [];
        if ($this->userCan('applications.view')) {
            $categories[] = 'applications';
        }
        if ($this->userCan('admissions.view')) {
            $categories[] = 'admissions';
        }
        if ($this->userCan('enrollments.view')) {
            $categories[] = 'enrollments';
        }

        return $categories;
    }

    protected function authorizeOperations(): void
    {
        $this->authorizeAny([
            'applications.view',
            'admissions.view',
            'enrollments.view',
        ]);
    }

    protected function authorizeAny(array $permissions): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        foreach ($permissions as $permission) {
            if ($this->userCan($permission)) {
                return;
            }
        }

        abort(403);
    }

    protected function userCan(string $permission): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isAbleTo') && $user->isAbleTo($permission)) {
            return true;
        }

        if (method_exists($user, 'hasPermission') && $user->hasPermission($permission)) {
            return true;
        }

        return match ($permission) {
            'applications.view' => $user->can('viewAny', StudentApplication::class),
            'admissions.view' => $user->can('viewAny', Admission::class),
            'enrollments.view' => $user->can('viewAny', Enrollment::class),
            default => false,
        };
    }
}
