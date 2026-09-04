<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\LifecycleOperationalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LifecycleReportsController extends Controller
{
    public function __construct(
        protected LifecycleOperationalService $ops
    ) {}

    public function index(Request $request)
    {
        $this->authorizeReport();

        $school = GetSchoolModel();
        $filters = array_filter([
            'academic_session_id' => $request->string('academic_session_id')->toString() ?: null,
            'status' => $request->input('status'),
            'class_level_id' => $request->string('class_level_id')->toString() ?: null,
            'source' => $request->string('source')->toString() ?: null,
            'has_application' => $request->string('has_application')->toString() ?: null,
            'origin' => $request->string('origin')->toString() ?: null,
            'finalized' => $request->input('finalized'),
            'date_from' => $request->string('date_from')->toString() ?: null,
            'date_to' => $request->string('date_to')->toString() ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        $sessionId = $filters['academic_session_id'] ?? null;

        $payload = [
            'applications' => $this->ops->applicationReport($school, $filters),
            'admissions' => $this->ops->admissionReport($school, $filters),
            'enrollments' => $this->ops->enrollmentReport($school, $filters),
            'placement' => $this->ops->placementReport($school, $filters),
            'funnel' => $this->ops->lifecycleFunnel($school, $sessionId),
            'filters' => $filters,
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Student/Lifecycle/Reports', $payload);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeReport();

        $school = GetSchoolModel();
        $filters = array_filter([
            'academic_session_id' => $request->string('academic_session_id')->toString() ?: null,
            'status' => $request->input('status'),
            'class_level_id' => $request->string('class_level_id')->toString() ?: null,
            'source' => $request->string('source')->toString() ?: null,
            'has_application' => $request->string('has_application')->toString() ?: null,
            'origin' => $request->string('origin')->toString() ?: null,
            'finalized' => $request->input('finalized'),
        ], fn ($v) => $v !== null && $v !== '');
        $sessionId = $filters['academic_session_id'] ?? null;
        $section = $request->string('section')->toString() ?: 'funnel';

        $data = match ($section) {
            'applications' => $this->ops->applicationReport($school, $filters),
            'admissions' => $this->ops->admissionReport($school, $filters),
            'enrollments' => $this->ops->enrollmentReport($school, $filters),
            'placement' => $this->ops->placementReport($school, $filters),
            default => ['funnel' => $this->ops->lifecycleFunnel($school, $sessionId)],
        };

        $filename = 'lifecycle-'.$section.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['key', 'value']);
            $this->flattenCsv($data, $out);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function flattenCsv(array $data, $out, string $prefix = ''): void
    {
        foreach ($data as $key => $value) {
            $label = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $this->flattenCsv($value, $out, $label);
            } else {
                fputcsv($out, [$label, $value]);
            }
        }
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
