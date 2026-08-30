<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student\Admission;
use App\Models\Student\StudentApplication;
use App\Services\Student\AdmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Phase 3 Admission lifecycle actions (issue / accept / decline / cancel / deadlines).
 * Keeps legacy AdmissionController CRUD intact where still used.
 */
class AdmissionLifecycleController extends Controller
{
    public function __construct(
        protected AdmissionService $admissionService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Admission::class);

        $school = GetSchoolModel();
        $query = Admission::query()
            ->with([
                'application:id,first_name,last_name,application_number,email,status',
                'classLevel:id,name',
                'academicSession:id,name',
                'student:id,first_name,last_name',
            ])
            ->where('school_id', $school->id)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest('offered_at');

        $admissions = $query->tableQuery($request);

        return Inertia::render('Student/Admissions/Index', [
            'admissions' => $admissions,
            'filters' => $request->only(['search', 'status', 'sort', 'sortOrder', 'perPage']),
        ]);
    }

    public function show(Admission $admission)
    {
        $this->authorize('view', $admission);

        $admission->load([
            'application',
            'classLevel:id,name',
            'academicSession:id,name',
            'student:id,first_name,last_name',
            'enrollment',
        ]);

        return Inertia::render('Student/Admissions/Show', [
            'admission' => $admission,
        ]);
    }

    public function storeFromApplication(Request $request, StudentApplication $application)
    {
        $this->authorize('issue', Admission::class);

        $school = GetSchoolModel();
        $data = $request->validate([
            'class_level_id' => ['nullable', 'uuid'],
            'academic_session_id' => ['nullable', 'uuid'],
            'acceptance_deadline' => ['nullable', 'date', 'after:now'],
            'registration_date' => ['nullable', 'date'],
            'registration_starts_at' => ['nullable', 'date'],
            'registration_ends_at' => ['nullable', 'date', 'after_or_equal:registration_starts_at'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $admission = $this->admissionService->createFromApplication(
                $application,
                $school,
                $request->user(),
                $data
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Failed to create admission from application', ['error' => $e->getMessage()]);

            return back()->with('error', 'Failed to create admission offer.');
        }

        return redirect()
            ->route('admissions.lifecycle.show', $admission)
            ->with('success', 'Admission offer issued.');
    }

    public function storeDirect(Request $request)
    {
        $this->authorize('direct', Admission::class);

        $school = GetSchoolModel();
        $data = $request->validate([
            'class_level_id' => ['required', 'uuid'],
            'academic_session_id' => ['required', 'uuid'],
            'acceptance_deadline' => ['nullable', 'date', 'after:now'],
            'registration_date' => ['nullable', 'date'],
            'registration_starts_at' => ['nullable', 'date'],
            'registration_ends_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $admission = $this->admissionService->createDirect($school, $request->user(), $data);

        return redirect()
            ->route('admissions.lifecycle.show', $admission)
            ->with('success', 'Direct admission offer issued.');
    }

    public function storeWalkIn(Request $request, StudentApplication $application)
    {
        $this->authorize('bypass', Admission::class);

        $school = GetSchoolModel();
        $data = $request->validate([
            'class_level_id' => ['nullable', 'uuid'],
            'academic_session_id' => ['nullable', 'uuid'],
            'acceptance_deadline' => ['nullable', 'date', 'after:now'],
            'registration_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $admission = $this->admissionService->createWalkInImmediate(
            $application,
            $school,
            $request->user(),
            $data
        );

        return redirect()
            ->route('admissions.lifecycle.show', $admission)
            ->with('success', 'Walk-in admission offer issued (application retained for audit).');
    }

    public function accept(Request $request, Admission $admission)
    {
        $this->authorize('accept', $admission);

        $this->admissionService->accept($admission, $request->user());

        return back()->with('success', 'Admission offer accepted.');
    }

    public function decline(Request $request, Admission $admission)
    {
        $this->authorize('decline', $admission);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->admissionService->decline($admission, $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Admission offer declined.');
    }

    public function cancel(Request $request, Admission $admission)
    {
        $this->authorize('cancel', $admission);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->admissionService->cancel($admission, $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Admission cancelled.');
    }

    public function expire(Request $request, Admission $admission)
    {
        $this->authorize('expire', $admission);

        $this->admissionService->expire($admission);

        return back()->with('success', 'Admission marked expired if eligible.');
    }

    public function updateDeadlines(Request $request, Admission $admission)
    {
        $this->authorize('manageDeadlines', $admission);

        $data = $request->validate([
            'acceptance_deadline' => ['nullable', 'date'],
            'registration_date' => ['nullable', 'date'],
            'registration_starts_at' => ['nullable', 'date'],
            'registration_ends_at' => ['nullable', 'date'],
        ]);

        $this->admissionService->updateDeadlines($admission, $request->user(), $data);

        return back()->with('success', 'Deadlines / registration window updated.');
    }
}
