<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\StudentApplication;
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
        $data = $this->ops->needsAttention($school, $request->integer('limit', 50));
        $data['items'] = $this->filterItemsByPermission($data['items'] ?? []);

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
        $data = $this->ops->upcomingDeadlines($school, $days, $request->integer('limit', 50));
        $data['items'] = $this->filterItemsByPermission($data['items'] ?? []);

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
        $data = $this->ops->recentlyCompleted($school, $days, $request->integer('limit', 50));
        $data['items'] = $this->filterItemsByPermission($data['items'] ?? []);

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
        $counts = $this->ops->dashboardCounts($school);

        if (! $this->userCan('applications.view')) {
            unset($counts['applications_awaiting_review']);
        }
        if (! $this->userCan('admissions.view')) {
            unset(
                $counts['offers_awaiting_acceptance'],
                $counts['offers_expiring_soon'],
                $counts['accepted_awaiting_registration']
            );
        }
        if (! $this->userCan('enrollments.view')) {
            unset(
                $counts['enrollments_in_progress'],
                $counts['ready_for_finalization'],
                $counts['awaiting_placement']
            );
        }

        return response()->json([
            'counts' => $counts,
            'needs_attention' => $this->filterItemsByPermission(
                $this->ops->needsAttention($school, 10)['items'] ?? []
            ),
            'upcoming_deadlines' => $this->filterItemsByPermission(
                $this->ops->upcomingDeadlines($school, 7, 10)['items'] ?? []
            ),
            'recently_completed' => $this->filterItemsByPermission(
                $this->ops->recentlyCompleted($school, 7, 10)['items'] ?? []
            ),
        ]);
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

    protected function filterItemsByPermission(array $items): array
    {
        return array_values(array_filter($items, function (array $item) {
            $type = $item['type'] ?? '';

            if (str_starts_with($type, 'application_')) {
                return $this->userCan('applications.view');
            }
            if (in_array($type, [
                'offer_awaiting_acceptance',
                'accepted_awaiting_registration',
                'acceptance_deadline',
                'registration_window_end',
                'offer_issued',
                'offer_accepted',
            ], true) || str_starts_with($type, 'offer_')) {
                return $this->userCan('admissions.view');
            }
            if (str_starts_with($type, 'enrollment_')) {
                return $this->userCan('enrollments.view');
            }

            return $this->userCan('applications.view')
                || $this->userCan('admissions.view')
                || $this->userCan('enrollments.view');
        }));
    }
}
