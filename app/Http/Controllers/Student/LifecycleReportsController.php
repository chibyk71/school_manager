<?php

namespace App\Http\Controllers\Student;

use App\Exports\Lifecycle\AdmissionsExport;
use App\Exports\Lifecycle\ApplicationsExport;
use App\Exports\Lifecycle\EnrollmentsExport;
use App\Exports\Lifecycle\PlacementsExport;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\Student\LifecycleOperationalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LifecycleReportsController extends Controller
{
    public function __construct(
        protected LifecycleOperationalService $ops
    ) {}

    public function index(Request $request)
    {
        $this->authorizeReport();

        $school = $this->currentSchool();
        $filters = $this->reportFilters($request);
        $sessionId = $filters['academic_session_id'] ?? null;

        return Inertia::render('Student/Lifecycle/Reports', [
            'applications' => $this->ops->applicationReport($school, $filters),
            'admissions' => $this->ops->admissionReport($school, $filters),
            'enrollments' => $this->ops->enrollmentReport($school, $filters),
            'placement' => $this->ops->placementReport($school, $filters),
            'funnel' => $this->ops->lifecycleFunnel($school, $sessionId),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorizeReport();

        $school = $this->currentSchool();
        $filters = $this->reportFilters($request);
        $section = $request->string('section')->toString() ?: 'applications';
        $format = strtolower($request->string('format')->toString() ?: 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            $format = 'csv';
        }

        if ($section === 'funnel') {
            abort(422, 'Funnel is a summary view; export applications, admissions, enrollments, or placements instead.');
        }

        $export = match ($section) {
            'admissions' => new AdmissionsExport($school, $filters),
            'enrollments' => new EnrollmentsExport($school, $filters),
            'placement', 'placements' => new PlacementsExport($school, $filters),
            'applications' => new ApplicationsExport($school, $filters),
            default => abort(422, 'Unknown report section for export.'),
        };

        $filename = 'lifecycle-'.$section.'-'.now()->format('Ymd-His').'.'.$format;
        $writerType = $format === 'xlsx'
            ? \Maatwebsite\Excel\Excel::XLSX
            : \Maatwebsite\Excel\Excel::CSV;

        return Excel::download($export, $filename, $writerType);
    }

    protected function reportFilters(Request $request): array
    {
        return array_filter([
            'academic_session_id' => $request->string('academic_session_id')->toString() ?: null,
            'status' => $request->input('status'),
            'class_level_id' => $request->string('class_level_id')->toString() ?: null,
            'class_section_id' => $request->string('class_section_id')->toString() ?: null,
            'section_id' => $request->string('section_id')->toString() ?: null,
            'source' => $request->string('source')->toString() ?: null,
            'has_application' => $request->string('has_application')->toString() ?: null,
            'origin' => $request->string('origin')->toString() ?: null,
            'finalized' => $request->input('finalized'),
            'date_from' => $request->string('date_from')->toString() ?: null,
            'date_to' => $request->string('date_to')->toString() ?: null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    protected function currentSchool(): School
    {
        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
        if (! $school instanceof School) {
            abort(403, 'School context required.');
        }

        return $school;
    }

    protected function authorizeReport(): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        if (method_exists($user, 'isAbleTo') && $user->isAbleTo('lifecycle-reports.view')) {
            return;
        }
        if (method_exists($user, 'hasPermission') && $user->hasPermission('lifecycle-reports.view')) {
            return;
        }

        $canApps = $user->can('viewAny', \App\Models\Student\StudentApplication::class);
        $canAdm = $user->can('viewAny', \App\Models\Student\Admission::class);
        $canEnr = $user->can('viewAny', \App\Models\Student\Enrollment::class);

        if ($canApps && $canAdm && $canEnr) {
            return;
        }

        abort(403);
    }
}
