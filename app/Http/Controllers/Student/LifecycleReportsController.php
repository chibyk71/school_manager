<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\LifecycleOperationalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 7 lifecycle reports – school-scoped aggregates over authoritative records.
 */
class LifecycleReportsController extends Controller
{
    public function __construct(
        protected LifecycleOperationalService $ops
    ) {}

    public function index(Request $request)
    {
        $this->authorizeReport();

        $school = GetSchoolModel();
        $sessionId = $request->string('academic_session_id')->toString() ?: null;
        $filters = array_filter(['academic_session_id' => $sessionId]);

        $payload = [
            'applications' => $this->ops->applicationReport($school, $filters),
            'admissions' => $this->ops->admissionReport($school, $filters),
            'enrollments' => $this->ops->enrollmentReport($school, $filters),
            'funnel' => $this->ops->lifecycleFunnel($school, $sessionId),
            'filters' => ['academic_session_id' => $sessionId],
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
        $sessionId = $request->string('academic_session_id')->toString() ?: null;
        $filters = array_filter(['academic_session_id' => $sessionId]);
        $section = $request->string('section')->toString() ?: 'funnel';

        $data = match ($section) {
            'applications' => $this->ops->applicationReport($school, $filters),
            'admissions' => $this->ops->admissionReport($school, $filters),
            'enrollments' => $this->ops->enrollmentReport($school, $filters),
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

        $allowed = $user->can('viewAny', \App\Models\Student\Enrollment::class)
            || $user->can('viewAny', \App\Models\Student\Admission::class)
            || $user->can('viewAny', \App\Models\Student\StudentApplication::class);

        if (! $allowed) {
            abort(403);
        }
    }
}
