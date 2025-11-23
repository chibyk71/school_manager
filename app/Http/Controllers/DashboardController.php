<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use App\Support\DashboardWidgets;
use App\Metrics\StudentMetric;
use App\Metrics\StaffMetric;
use App\Metrics\FinanceMetric;
use App\Metrics\AttendanceMetric;        // ← One unified metric
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
                'widgets'   => DashboardWidgets::getForDashboard(strtolower(str_replace('Dashboard', '', $component))),
                'data'      => $data,
            ]);
        }

        return Inertia::render('Dashboard/Index', [
            'component' => $component,
            'title'     => Str::title(str_replace('Dashboard', ' Dashboard', $component)),
            'widgets'   => DashboardWidgets::getForDashboard(strtolower(str_replace('Dashboard', '', $component))),
            'data'      => $data,
        ]);
    }

    private function adminData(): array
    {
        $attendance = app(AttendanceMetric::class);

        return [
            'cards' => [
                app(StudentMetric::class)->total(),
                app(StaffMetric::class)->total(),
                app(StaffMetric::class)->academic(),
                app(FinanceMetric::class)->collected(['month' => now()->month]),
                app(FinanceMetric::class)->pending(),
                $attendance->studentTodayRate(),
                $attendance->staffTodayRate(),           // ← New
                $attendance->pendingLeaves(),           // ← New: HR visibility
                app(StudentMetric::class)->active(),
            ],
            'charts' => [
                'revenue'         => app(FinanceMetric::class)->methodBreakdown(),
                'enrollment'      => app(StudentMetric::class)->enrollmentTrendYTD(),
                'staff_dept'      => app(StaffMetric::class)->departmentBreakdown(),
                'student_attendance' => $attendance->studentTrend(),
                'staff_attendance'   => $attendance->staffTrend(),
            ],
        ];
    }

    private function academicData(): array
    {
        $myClasses = auth()->user()->teacherClasses()->pluck('id')->toArray();
        $attendance = app(AttendanceMetric::class);

        return [
            'cards' => [
                app(StudentMetric::class)->total(['class_id' => $myClasses]),
                $attendance->studentTodayRate(),
                app(AcademicPerformanceMetric::class)->averageGrade(),
                app(AcademicPerformanceMetric::class)->atRisk(),
                app(AcademicPerformanceMetric::class)->submissionRate(),
            ],
            'charts' => [
                'subject_perf' => app(AcademicPerformanceMetric::class)->subjectBreakdown(),
                'grade_trend'  => app(AcademicPerformanceMetric::class)->termTrend(),
                'attendance'   => $attendance->studentTrend(),
            ],
        ];
    }

    private function financeData(): array
    {
        return [
            'cards' => [
                app(FinanceMetric::class)->collected(['month' => now()->month]),
                app(FinanceMetric::class)->pending(),
                app(FinanceMetric::class)->collectionRate(),
            ],
            'charts' => [
                'methods' => app(FinanceMetric::class)->methodBreakdown(),
            ],
        ];
    }

    private function supportData(): array
    {
        $attendance = app(AttendanceMetric::class);

        return [
            'cards' => [
                app(HealthMetric::class)->alertsToday(),
                $attendance->studentTodayRate(),
                $attendance->staffTodayRate(),
            ],
            'charts' => [
                'incidents' => app(HealthMetric::class)->incidentTrend(),
                'types'     => app(HealthMetric::class)->typeBreakdown(),
            ],
        ];
    }

    private function studentData(): array
    {
        $student = auth()->user()->student;
        $attendance = app(AttendanceMetric::class);

        return [
            'cards' => [
                ['value' => $student->attendance_rate . '%', 'title' => 'My Attendance'],
                ['value' => $student->current_average,       'title' => 'My Average'],
                ['value' => $student->fees_balance,          'title' => 'Fees Owed'],
                $attendance->studentTodayRate(), // optional personal card
            ],
            'charts' => [
                'grade_trend'  => app(AcademicPerformanceMetric::class)->termTrend(),
                'attendance'   => $attendance->studentTrend(),
            ],
        ];
    }

    private function parentData(): array
    {
        $children = auth()->user()->children;

        return [
            'cards' => $children->map(fn($c) => [
                'value' => $c->name,
                'title' => 'Child',
                'image' => $c->photo,
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
                'staff_dept'   => app(StaffMetric::class)->departmentBreakdown(),
                'enrollment'   => app(StudentMetric::class)->enrollmentTrendYTD(),
                'student_att'  => $attendance->studentTrend(),
                'staff_att'    => $attendance->staffTrend(),
            ],
            'recentLogs' => \Spatie\Activitylog\Models\Activity::latest()
                ->take(5)
                ->get()
                ->map(fn($log) => [
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
