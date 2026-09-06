<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\StudentApplication;
use App\Services\Student\LifecycleOperationalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use ReflectionMethod;

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
        $data = $this->callOpsFeed('needsAttention', $school, $limit, $categories);

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
        $data = $this->callOpsFeed('upcomingDeadlines', $school, $request->integer('limit', 50), $categories, $days);

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
        $data = $this->callOpsFeed('recentlyCompleted', $school, $request->integer('limit', 50), $categories, $days);

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
        $session = app(\App\Services\AcademicCalendarService::class)->currentSession();
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
            'needs_attention' => $this->callOpsFeed('needsAttention', $school, 10, $categories)['items'] ?? [],
            'upcoming_deadlines' => $this->callOpsFeed(
                'upcomingDeadlines',
                $school,
                10,
                array_values(array_intersect($categories, ['admissions', 'enrollments'])),
                7
            )['items'] ?? [],
            'recently_completed' => $this->callOpsFeed('recentlyCompleted', $school, 10, $categories, 7)['items'] ?? [],
        ]);
    }

    /**
     * Prefer service-level category scoping when available; fall back to post-filter.
     *
     * @param  list<string>  $categories
     * @return array{items: list<array>, total: int, returned_count?: int}
     */
    protected function callOpsFeed(string $method, $school, int $limit, array $categories, ?int $withinDays = null): array
    {
        $ref = new ReflectionMethod($this->ops, $method);
        $paramCount = $ref->getNumberOfParameters();

        if ($method === 'needsAttention') {
            $data = $paramCount >= 3
                ? $this->ops->needsAttention($school, $limit, $categories)
                : $this->ops->needsAttention($school, $limit);
        } elseif ($method === 'upcomingDeadlines') {
            $days = $withinDays ?? 14;
            $data = $paramCount >= 4
                ? $this->ops->upcomingDeadlines($school, $days, $limit, $categories)
                : $this->ops->upcomingDeadlines($school, $days, $limit);
        } else {
            $days = $withinDays ?? 14;
            $data = $paramCount >= 4
                ? $this->ops->recentlyCompleted($school, $days, $limit, $categories)
                : $this->ops->recentlyCompleted($school, $days, $limit);
        }

        if ($paramCount < 3 || ($method !== 'needsAttention' && $paramCount < 4)) {
            $data = $this->scopeFeedToCategories($data, $categories, $limit);
        }

        return $data;
    }

    /**
     * Fallback when service does not yet accept $categories.
     *
     * @param  array{items?: list<array>, total?: int}  $data
     * @param  list<string>  $categories
     * @return array{items: list<array>, total: int, returned_count: int}
     */
    protected function scopeFeedToCategories(array $data, array $categories, int $limit): array
    {
        $items = array_values(array_filter($data['items'] ?? [], function (array $item) use ($categories) {
            $type = $item['type'] ?? '';
            if (str_starts_with($type, 'application_')) {
                return in_array('applications', $categories, true);
            }
            if (in_array($type, [
                'offer_awaiting_acceptance',
                'accepted_awaiting_registration',
                'acceptance_deadline',
                'registration_window_end',
                'offer_issued',
                'offer_accepted',
            ], true) || str_starts_with($type, 'offer_')) {
                return in_array('admissions', $categories, true);
            }
            if (str_starts_with($type, 'enrollment_')) {
                return in_array('enrollments', $categories, true);
            }

            return false;
        }));

        $items = array_slice($items, 0, $limit);

        return [
            'items' => $items,
            'total' => count($items),
            'returned_count' => count($items),
        ];
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
