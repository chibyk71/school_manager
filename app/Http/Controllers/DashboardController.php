<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use App\Support\DashboardWidgets;
use App\Facades\Academic;
use App\Services\Student\LifecycleOperationalService;
use App\Metrics\StudentMetric;
use App\Metrics\StaffMetric;
use App\Metrics\FinanceMetric;
use App\Metrics\AttendanceMetric;
use App\Metrics\AcademicPerformanceMetric;
use App\Metrics\HealthMetric;
use App\Metrics\SystemMetric;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    protected const CACHE_TTL = 10;

    public function index(Request $request): Response|JsonResponse
    {
        $user = Auth::user();
        $category = $user->getPrimaryCategory();

        $dashboardMap = [
            'leadership'       => 'AdminDashboard',
            'academic'         => 'AcademicDashboard',
            'finance'          => 'FinanceDashboard',
            'student_support'  => 'SupportDashboard',
            'hostel'           => 'SupportDashboard',
            'sport'            => 'SupportDashboard',
            'transport'        => 'SupportDashboard',
            'ict'              => 'AcademicDashboard',
            'operations'       => 'SupportDashboard',
            'communication'    => 'AdminDashboard',
            'admissions'       => 'AdminDashboard',
            'hr'               => 'AdminDashboard',
            'student'          => 'StudentDashboard',
            'parent'           => 'ParentDashboard',
        ];

        $component = $dashboardMap[$category] ?? 'GeneralDashboard';
        $cacheKey = "dashboard.{$category}.{$user->id}";

        $data = Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL), function () use ($component) {
            return match ($component) {
                'AdminDashboard'     => $this->adminData(),
                'AcademicDashboard'  => $this->academicData(),
                'FinanceDashboard'   => $this->financeData(),
                'SupportDashboard'   => $this->supportData(),
                'StudentDashboard'   => $this->studentData(),
                'ParentDashboard'    => $this->parentData(),
                default              => $this->generalData(),
            };
        });

        if ($request->expectsJson()) {
            return response()->json([
                'dashboard' => $component,
                'data' => $data,
            ]);
        }

        return Inertia::render("Dashboard/{$component}", [
            'data' => $data,
            'widgets' => DashboardWidgets::forCategory($category),
        ]);
    }

    private function adminData(): array
    {
        $lifecycle = [];
        try {
            $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
            if ($school) {
                // Session resolution is owned by Academic — pass it in.
                // dashboardCounts(null) intentionally returns zeros (session boundary).
                $session = Academic::currentSession();
                $lifecycle = app(LifecycleOperationalService::class)->dashboardCounts($school, $session);
            }
        } catch (\Throwable $e) {
            $lifecycle = [];
        }

        $attendance = app(AttendanceMetric::class);

        return [
            'cards' => [
                app(StudentMetric::class)->total(),
                app(StaffMetric::class)->total(),
                app(StaffMetric::class)->academic(),
                $attendance->studentTodayRate(),
                $attendance->staffTodayRate(),
                app(FinanceMetric::class)->collectionRate(),
                $attendance->pendingLeaves(),
            ],
            'lifecycle' => $lifecycle,
            'charts' => [
                'staff_dept'   => app(StaffMetric::class)->departmentBreakdown(),
                'enrollment'   => app(StudentMetric::class)->enrollmentTrendYTD(),
                'student_att'  => $attendance->studentTrend(),
                'staff_att'    => $attendance->staffTrend(),
            ],
            'recentLogs' => \Spatie\Activitylog\Models\Activity::latest()
                ->take(5)
                ->get()
                ->map(fn ($log) => [
                    'id'          => $log->id,
                    'description' => $log->description,
                    'icon'        => $this->logIcon($log->description),
                    'time'        => $log->created_at->diffForHumans(),
                ])->toArray(),
        ];
    }

    private function academicData(): array
    {
        $attendance = app(AttendanceMetric::class);
        $academic = app(AcademicPerformanceMetric::class);

        return [
            'cards' => [
                app(StudentMetric::class)->total(),
                $academic->averageScore(),
                $academic->studentsAtRisk(),
                $academic->assignmentCompletionRate(),
                $academic->topClass(),
                $attendance->studentTodayRate(),
            ],
            'charts' => [
                'performance' => $academic->termTrend(),
                'student_att' => $attendance->studentTrend(),
            ],
        ];
    }

    private function financeData(): array
    {
        $finance = app(FinanceMetric::class);

        return [
            'cards' => [
                $finance->outstandingFees(),
                $finance->collectionRate(),
                $finance->todayCollections(),
            ],
            'charts' => [
                'collections' => $finance->collectionTrend(),
            ],
        ];
    }

    private function supportData(): array
    {
        $attendance = app(AttendanceMetric::class);

        return [
            'cards' => [
                app(StudentMetric::class)->total(),
                $attendance->studentTodayRate(),
                $attendance->pendingLeaves(),
            ],
            'charts' => [
                'student_att' => $attendance->studentTrend(),
            ],
        ];
    }

    private function studentData(): array
    {
        $student = auth()->user()->student;
        $attendance = app(AttendanceMetric::class);

        return [
            'cards' => [
                ['value' => $student->current_class ?? '—', 'title' => 'My Class'],
                ['value' => $student->current_average ?? '—', 'title' => 'My Average'],
                ['value' => $student->fees_balance ?? '—', 'title' => 'Fees Owed'],
                $attendance->studentTodayRate(),
            ],
            'charts' => [
                'grade_trend' => app(AcademicPerformanceMetric::class)->termTrend(),
                'attendance'  => $attendance->studentTrend(),
            ],
        ];
    }

    private function parentData(): array
    {
        $children = auth()->user()->children ?? collect();

        return [
            'cards' => $children->map(fn ($c) => [
                'value' => $c->name,
                'title' => 'Child',
                'image' => $c->photo ?? null,
            ])->toArray(),
            'charts' => [],
        ];
    }

    private function generalData(): array
    {
        $attendance = app(AttendanceMetric::class);

        return [
            'cards' => [
                app(StudentMetric::class)->total(),
                app(StaffMetric::class)->total(),
                app(StaffMetric::class)->academic(),
                $attendance->studentTodayRate(),
                $attendance->staffTodayRate(),
                app(FinanceMetric::class)->collectionRate(),
                $attendance->pendingLeaves(),
            ],
            'charts' => [
                'staff_dept'  => app(StaffMetric::class)->departmentBreakdown(),
                'enrollment'  => app(StudentMetric::class)->enrollmentTrendYTD(),
                'student_att' => $attendance->studentTrend(),
                'staff_att'   => $attendance->staffTrend(),
            ],
            'recentLogs' => \Spatie\Activitylog\Models\Activity::latest()
                ->take(5)
                ->get()
                ->map(fn ($log) => [
                    'id'          => $log->id,
                    'description' => $log->description,
                    'icon'        => $this->logIcon($log->description),
                    'time'        => $log->created_at->diffForHumans(),
                ])->toArray(),
        ];
    }

    private function logIcon(string $description): string
    {
        return match (true) {
            str_contains($description, 'created') => 'pi pi-plus-circle text-green-600',
            str_contains($description, 'updated') => 'pi pi-pencil text-blue-600',
            str_contains($description, 'deleted') => 'pi pi-trash text-red-600',
            default => 'pi pi-info-circle text-gray-600',
        };
    }
}
