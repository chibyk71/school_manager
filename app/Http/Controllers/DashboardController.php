<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laratrust\LaratrustFacade as Laratrust;

/**
 * Unified dashboard controller.
 * Renders widget configuration only — each widget fetches its own data via API.
 * Uses permission structure to dynamically build widget groups.
 */
class DashboardController extends Controller
{
    /**
     * Display the dashboard with widget configuration.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request): \Inertia\Response
    {
        permitted('dashboard.view');

        $user = auth()->user();
        $permissions = ($user)->permissions()->pluck('name')->toArray();

        $widgets = $this->buildWidgetGroups($permissions);

        return Inertia::render('Dashboard/Index', [
            'widgets' => $widgets,
        ]);
    }

    /**
     * Build widget groups based on user permissions.
     *
     * @param array $permissions
     * @return array<string, array<string, array{title:string,icon:string,endpoint:string}>>
     */
    protected function buildWidgetGroups(array $permissions): array
    {
        $groups = [];

        // Leadership / Admin Group
        if ($this->hasAnyPermission($permissions, [
            'view-schools', 'create-school', 'update-school', 'delete-school',
            'departments.view', 'departments.create', 'departments.assign-role'
        ])) {
            $groups['leadership'] = [
                'schoolOverview' => [
                    'title' => 'School Overview',
                    'icon' => 'pi-building',
                    'endpoint' => '/api/dashboard/school-overview',
                ],
                'staffSummary' => [
                    'title' => 'Staff Summary',
                    'icon' => 'pi-users',
                    'endpoint' => '/api/dashboard/staff-summary',
                ],
                'departmentRoles' => [
                    'title' => 'Department Roles',
                    'icon' => 'pi-sitemap',
                    'endpoint' => '/api/dashboard/department-roles',
                ],
            ];
        }

        // Academic Management Group
        if ($this->hasAnyPermission($permissions, [
            'class-sections.view', 'subjects.view', 'teacher-assignments.view',
            'timetables.view', 'timetable-details.view', 'terms.view'
        ])) {
            $groups['academic'] = [
                'classSections' => [
                    'title' => 'Class Sections',
                    'icon' => 'pi-th-large',
                    'endpoint' => '/api/dashboard/class-sections',
                ],
                'subjects' => [
                    'title' => 'Subjects',
                    'icon' => 'pi-book',
                    'endpoint' => '/api/dashboard/subjects',
                ],
                'teacherAssignments' => [
                    'title' => 'Teacher Assignments',
                    'icon' => 'pi-id-card',
                    'endpoint' => '/api/dashboard/teacher-assignments',
                ],
                'timetableSummary' => [
                    'title' => 'Timetable',
                    'icon' => 'pi-clock',
                    'endpoint' => '/api/dashboard/timetable-summary',
                ],
            ];
        }

        // Student & Parent Group
        if ($this->hasPermission($permissions, 'view-parent-dashboard') || $this->hasPermission($permissions, 'view-student-dashboard')) {
            $groups['myStudents'] = [
                'childProgress' => [
                    'title' => 'My Children',
                    'icon' => 'pi-user',
                    'endpoint' => '/api/dashboard/parent/children',
                ],
                'myGrades' => [
                    'title' => 'My Grades',
                    'icon' => 'pi-star',
                    'endpoint' => '/api/dashboard/student/grades',
                ],
                'assignmentsDue' => [
                    'title' => 'Assignments Due',
                    'icon' => 'pi-file-edit',
                    'endpoint' => '/api/dashboard/student/assignments',
                ],
            ];
        }

        // Transport & Logistics Group
        if ($this->hasAnyPermission($permissions, [
            'routes.view', 'vehicles.view', 'vehicles.assign-driver',
            'vehicle-documents.view', 'vehicle-expenses.view'
        ])) {
            $groups['transport'] = [
                'routes' => [
                    'title' => 'Transport Routes',
                    'icon' => 'pi-map',
                    'endpoint' => '/api/dashboard/routes',
                ],
                'vehicles' => [
                    'title' => 'Vehicles',
                    'icon' => 'pi-car',
                    'endpoint' => '/api/dashboard/vehicles',
                ],
                'vehicleExpenses' => [
                    'title' => 'Vehicle Expenses',
                    'icon' => 'pi-money-bill',
                    'endpoint' => '/api/dashboard/vehicle-expenses',
                ],
            ];
        }

        // Hostel Management Group
        if ($this->hasAnyPermission($permissions, [
            'hostel.view-any', 'hostel-room.view-any', 'hostel-assignment.view-any'
        ])) {
            $groups['hostel'] = [
                'hostelOverview' => [
                    'title' => 'Hostel Overview',
                    'icon' => 'pi-home',
                    'endpoint' => '/api/dashboard/hostel-overview',
                ],
                'roomAllocation' => [
                    'title' => 'Room Allocation',
                    'icon' => 'pi-bed',
                    'endpoint' => '/api/dashboard/hostel-rooms',
                ],
                'studentAssignments' => [
                    'title' => 'Student Assignments',
                    'icon' => 'pi-users',
                    'endpoint' => '/api/dashboard/hostel-assignments',
                ],
            ];
        }

        // Finance & Reports Group
        if ($this->hasPermission($permissions, 'finance-reports.view')) {
            $groups['finance'] = [
                'financialSummary' => [
                    'title' => 'Financial Summary',
                    'icon' => 'pi-wallet',
                    'endpoint' => '/api/dashboard/finance-summary',
                ],
                'feeCollection' => [
                    'title' => 'Fee Collection',
                    'icon' => 'pi-chart-line',
                    'endpoint' => '/api/dashboard/fee-collection',
                ],
            ];
        }

        // Notices & Communication
        if ($this->hasAnyPermission($permissions, [
            'notices.view', 'notices.create', 'notices.mark-read'
        ])) {
            $groups['communication'] = [
                'notices' => [
                    'title' => 'School Notices',
                    'icon' => 'pi-bell',
                    'endpoint' => '/api/dashboard/notices',
                ],
            ];
        }

        // Fallback: Always show if no other group
        if (empty($groups)) {
            $groups['general'] = [
                'welcome' => [
                    'title' => 'Welcome',
                    'icon' => 'pi-info-circle',
                    'endpoint' => '/api/dashboard/welcome',
                ],
            ];
        }

        return $groups;
    }

    /**
     * Check if user has any of the given permissions.
     */
    protected function hasAnyPermission(array $userPermissions, array $required): bool
    {
        return collect($userPermissions)->intersect($required)->isNotEmpty();
    }

    /**
     * Check if user has a specific permission.
     */
    protected function hasPermission(array $userPermissions, string $permission): bool
    {
        return in_array($permission, $userPermissions);
    }
}
