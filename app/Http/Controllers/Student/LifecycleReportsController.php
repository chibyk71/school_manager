<?php

namespace App\Http\Controllers\Student;

use App\Exports\Lifecycle\AdmissionsExport;
use App\Exports\Lifecycle\ApplicationsExport;
use App\Exports\Lifecycle\EnrollmentsExport;
use App\Exports\Lifecycle\FunnelExport;
use App\Exports\Lifecycle\PlacementsExport;
use App\Http\Controllers\Controller;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use App\Models\School;
use App\Services\AcademicSessionService;
use App\Services\Student\LifecycleOperationalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LifecycleReportsController extends Controller
{
    public function __construct(
        protected LifecycleOperationalService $ops,
        protected AcademicSessionService $academicSessions
    ) {}

    public function index(Request $request)
    {
        $school = GetSchoolModel();
        $this->authorizeReports($school);

        return Inertia::render('Student/Lifecycle/Reports', [
            'sessions' => $this->sessionOptions($school),
            'classLevels' => ClassLevel::query()
                ->whereHas('schoolSection', fn ($q) => $q->where('school_id', $school->id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'classSections' => ClassSection::query()
                ->whereHas('classLevel.schoolSection', fn ($q) => $q->where('school_id', $school->id))
                ->orderBy('name')
                ->get(['id', 'name', 'class_level_id']),
            'filters' => $request->only([
                'academic_session_id',
                'class_level_id',
                'class_section_id',
                'status',
                'from',
                'to',
            ]),
        ]);
    }

    public function applications(Request $request)
    {
        return $this->exportOrJson($request, 'applications');
    }

    public function admissions(Request $request)
    {
        return $this->exportOrJson($request, 'admissions');
    }

    public function enrollments(Request $request)
    {
        return $this->exportOrJson($request, 'enrollments');
    }

    public function placements(Request $request)
    {
        return $this->exportOrJson($request, 'placements');
    }

    public function funnel(Request $request)
    {
        return $this->exportOrJson($request, 'funnel');
    }

    protected function exportOrJson(Request $request, string $type)
    {
        $school = GetSchoolModel();
        $this->authorizeReports($school);

        $filters = $request->only([
            'academic_session_id',
            'class_level_id',
            'class_section_id',
            'status',
            'from',
            'to',
        ]);

        if ($request->wantsJson() && ! $request->boolean('export')) {
            return response()->json($this->ops->reportData($school, $type, $filters));
        }

        $export = match ($type) {
            'applications' => new ApplicationsExport($school, $filters),
            'admissions' => new AdmissionsExport($school, $filters),
            'enrollments' => new EnrollmentsExport($school, $filters),
            'placements' => new PlacementsExport($school, $filters),
            'funnel' => new FunnelExport($school, $filters),
            default => abort(404),
        };

        $filename = "lifecycle-{$type}-" . now()->format('Ymd-His') . '.xlsx';

        return Excel::download($export, $filename);
    }

    /**
     * Session filter options — owned by Academic (not Lifecycle).
     */
    protected function sessionOptions(School $school): array
    {
        return $this->academicSessions->sessionsForSchool($school);
    }

    protected function authorizeReports(School $school): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        // Reports require at least one lifecycle view permission.
        $ok = false;
        foreach (['applications.view', 'admissions.view', 'enrollments.view'] as $perm) {
            if (method_exists($user, 'isAbleTo') && $user->isAbleTo($perm)) {
                $ok = true;
                break;
            }
            if (method_exists($user, 'hasPermission') && $user->hasPermission($perm)) {
                $ok = true;
                break;
            }
        }

        if (! $ok) {
            abort(403);
        }
    }
}
