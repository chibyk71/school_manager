<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laratrust\LaratrustFacade;

/**
 * DashboardController
 *
 * One entry point → many dashboards.
 *
 * 1. Resolve the *entry* dashboard component (SuperAdmin, Leadership, …)
 * 2. Build a rich `can` permission array
 * 3. Render `Dashboard/BaseDashboard` – the UI decides what to show.
 *
 * UI location:
 *   resources/js/Pages/Dashboard/BaseDashboard.vue
 *   resources/js/Pages/Dashboard/SuperAdminDashboard.vue   (optional full page)
 */
class DashboardController extends Controller
{
    /** -----------------------------------------------------------------
     *  Role → Entry-point component map
     *  -----------------------------------------------------------------
     *  The value is the **exact Inertia component name** (without .vue).
     *  Add new roles here – no other code changes.
     */
    private const ENTRY_MAP = [
        // SaaS super-admin
        'super-admin' => 'SuperAdminDashboard',

        // School leadership
        'admin' => 'LeadershipDashboard',
        'principal' => 'LeadershipDashboard',
        'vice_principal_academic' => 'LeadershipDashboard',
        'vice_principal_admin' => 'LeadershipDashboard',

        // Academic staff (fallback entry point)
        'teacher' => 'AcademicDashboard',
        'hod' => 'AcademicDashboard',
        'subject-coordinator' => 'AcademicDashboard',
        'class-teacher' => 'AcademicDashboard',
        'lesson-planner' => 'AcademicDashboard',
        'exam-officer' => 'AcademicDashboard',
        'librarian' => 'AcademicDashboard',

        // Finance
        'bursar' => 'FinanceDashboard',
        'accountant' => 'FinanceDashboard',

        // Administration
        'school_secretary' => 'AdministrativeDashboard',
        'administrative_officer' => 'AdministrativeDashboard',
        'records_officer' => 'AdministrativeDashboard',
        'public_relations_officer' => 'AdministrativeDashboard',

        // Operations (ICT, Maintenance, Transport, Catering, Boarding)
        'head_ict_mis' => 'OperationsDashboard',
        'ict_officer' => 'OperationsDashboard',
        'systems_administrator' => 'OperationsDashboard',
        'it-support' => 'OperationsDashboard',
        'head_maintenance' => 'OperationsDashboard',
        'head_security' => 'OperationsDashboard',
        'transport_officer' => 'OperationsDashboard',
        'catering_manager' => 'OperationsDashboard',
        'boarding_house_master' => 'OperationsDashboard',
    ];

    public function __invoke(Request $request): \Inertia\Response
    {
        $user = $request->user();
        if (!$user) {
            abort(403, 'Unauthenticated.');
        }

        $school = GetSchoolModel();

        // 1. Resolve entry component
        $entryComponent = $this->resolveEntryComponent($user);

        // 2. Build `can` array
        $can = $this->buildCanArray($user);

        // 3. Activity log
        activity()
            ->causedBy($user)
            ->performedOn($school)
            ->log("Accessed {$entryComponent}");

        // 4. Render the *base* dashboard – UI decides what to show
        return Inertia::render('Dashboard/BaseDashboard', [
            'entryComponent' => $entryComponent,   // optional: for full-page overrides
            'user' => $user->only(['id', 'name', 'email', 'avatar']),
            'school' => $school ? [
                'id' => $school->id,
                'name' => $school->name,
                'logo' => $school->logo_url ?? null,
                'sectionCount' => $school->sections()->count(),
            ] : null,
            'roles' => $user->roles->pluck('name')->toArray(),
            'can' => $can,
        ]);
    }

    private function resolveEntryComponent($user): string
    {
        foreach (self::ENTRY_MAP as $role => $component) {
            if (LaratrustFacade::hasRole($role)) {
                return $component;
            }
        }

        // Fallback – any authenticated user gets the generic base layout
        return 'Dashboard';
    }

    private function buildCanArray($user): array
    {
        $can = [];
        foreach (self::PERMISSIONS as $key => $permission) {
            $can[$key] = $user->can($permission);
        }
        return $can;
    }
}
