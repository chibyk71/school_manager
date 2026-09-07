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
use App\Services\AcademicCalendarService;
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
        protected AcademicCalendarService $calendar
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
            'academicSessions' => $this->sessionOptions($school),
            'classLevels' => $this->classLevelOptions($school),
            'classSections' => $this->classSectionOptions($school, $filters['class_level_id'] ?? null),
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

        $export = match ($section) {
            'admissions' => new AdmissionsExport($school, $filters),
            'enrollments' => new EnrollmentsExport($school, $filters),
            'placement', 'placements' => new PlacementsExport($school, $filters),
            'funnel' => new FunnelExport($school, $filters),
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
        $raw = array_filter([
            'academic_session_id' => $request->string('academic_session_id')->toString() ?: null,
            'status' => $request->input('status'),
            'class_level_id' => $request->string('class_level_id')->toString() ?: null,
            'class_section_id' => $request->string('class_section_id')->toString()
                ?: ($request->string('section_id')->toString() ?: null),
            'section_id' => $request->string('section_id')->toString() ?: null,
            'source' => $request->string('source')->toString() ?: null,
            'has_application' => $request->string('has_application')->toString() ?: null,
            'origin' => $request->string('origin')->toString() ?: null,
            'finalized' => $request->input('finalized'),
            'review_state' => $request->string('review_state')->toString() ?: null,
            'acceptance_state' => $request->string('acceptance_state')->toString() ?: null,
            'date_from' => $request->string('date_from')->toString() ?: null,
            'date_to' => $request->string('date_to')->toString() ?: null,
            'deadline_from' => $request->string('deadline_from')->toString() ?: null,
            'deadline_to' => $request->string('deadline_to')->toString() ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        // Inclusive day bounds applied once in the domain service.
        return $this->ops->normalizeReportFilters($raw);
    }

    /**
     * Session filter options — owned by Academic Calendar (not Lifecycle).
     *
     * @return list<array{id: string, name: string, is_current: bool}>
     */
    protected function sessionOptions(School $school): array
    {
        return $this->calendar->sessionsForSchool($school);
    }

    protected function classLevelOptions(School $school): array
    {
        if (! class_exists(ClassLevel::class) || ! Schema::hasTable('class_levels')) {
            return [];
        }

        return ClassLevel::query()
            ->where('school_id', $school->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($row) => ['id' => (string) $row->id, 'name' => (string) $row->name])
            ->all();
    }

    protected function classSectionOptions(School $school, ?string $classLevelId = null): array
    {
        if (! class_exists(ClassSection::class) || ! Schema::hasTable('class_sections')) {
            return [];
        }

        $q = ClassSection::query()->where('school_id', $school->id)->orderBy('name');
        if ($classLevelId) {
            $q->where('class_level_id', $classLevelId);
        }

        return $q->get(['id', 'name', 'class_level_id'])
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'class_level_id' => $row->class_level_id ? (string) $row->class_level_id : null,
            ])
            ->all();
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
