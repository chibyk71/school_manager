<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student\Enrollment;
use App\Models\Student\EnrollmentRequirementInstance;
use App\Services\Student\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class EnrollmentController extends Controller
{
    public function __construct(
        protected EnrollmentService $enrollmentService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Enrollment::class);

        $school = GetSchoolModel();
        $query = Enrollment::query()
            ->with([
                'student:id,first_name,last_name,profile_id',
                'academicSession:id,name',
                'admission:id,status,application_id',
            ])
            ->where('school_id', $school->id)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest();

        $enrollments = $query->paginate($request->integer('per_page', 15))->withQueryString();

        return Inertia::render('Student/Enrollments/Index', [
            'enrollments' => $enrollments,
            'filters' => ['status' => $request->status],
            'statuses' => Enrollment::STATUSES,
        ]);
    }

    public function show(Enrollment $enrollment)
    {
        $this->authorize('view', $enrollment);

        $school = GetSchoolModel();
        if ($enrollment->school_id !== $school->id) {
            abort(404);
        }

        $enrollment->load([
            'student',
            'academicSession:id,name',
            'admission.application',
            'requirementInstances.definition',
        ]);

        $readiness = $this->enrollmentService->evaluateReadiness($enrollment);

        return Inertia::render('Student/Enrollments/Show', [
            'enrollment' => $enrollment,
            'readiness' => $readiness,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Enrollment::class);

        $school = GetSchoolModel();

        $data = $request->validate([
            'academic_session_id' => ['required', 'uuid'],
            'admission_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string'],
            'biodata' => ['nullable', 'array'],
            'biodata.first_name' => ['nullable', 'string', 'max:100'],
            'biodata.last_name' => ['nullable', 'string', 'max:100'],
            'biodata.email' => ['nullable', 'email', 'max:255'],
            'biodata.phone' => ['nullable', 'string', 'max:50'],
            'biodata.date_of_birth' => ['nullable', 'date'],
            'biodata.gender' => ['nullable', 'string', 'max:30'],
            'source' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $enrollment = $this->enrollmentService->start($school, $request->user(), $data);

            return redirect()
                ->route('enrollments.show', $enrollment)
                ->with('success', 'Enrollment started.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Enrollment start failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['error' => 'Unable to start enrollment.']);
        }
    }

    public function updateBiodata(Request $request, Enrollment $enrollment)
    {
        $this->authorize('update', $enrollment);

        $school = GetSchoolModel();
        if ($enrollment->school_id !== $school->id) {
            abort(404);
        }

        $data = $request->validate([
            'biodata' => ['required', 'array'],
            'biodata.first_name' => ['nullable', 'string', 'max:100'],
            'biodata.last_name' => ['nullable', 'string', 'max:100'],
            'biodata.email' => ['nullable', 'email', 'max:255'],
            'biodata.phone' => ['nullable', 'string', 'max:50'],
            'biodata.date_of_birth' => ['nullable', 'date'],
            'biodata.gender' => ['nullable', 'string', 'max:30'],
            'biodata.middle_name' => ['nullable', 'string', 'max:100'],
            'biodata.nationality' => ['nullable', 'string', 'max:100'],
            'biodata.address_line_1' => ['nullable', 'string', 'max:255'],
            'biodata.city' => ['nullable', 'string', 'max:100'],
            'biodata.state' => ['nullable', 'string', 'max:100'],
            'biodata.postal_code' => ['nullable', 'string', 'max:30'],
            'biodata.country' => ['nullable', 'string', 'max:100'],
        ]);

        $this->enrollmentService->updateBiodata($enrollment, $request->user(), $data['biodata']);

        return back()->with('success', 'Biodata updated.');
    }

    public function satisfyRequirement(Request $request, Enrollment $enrollment, EnrollmentRequirementInstance $instance)
    {
        $this->authorize('manageRequirements', $enrollment);

        $school = GetSchoolModel();
        if ($enrollment->school_id !== $school->id) {
            abort(404);
        }

        $data = $request->validate([
            'document_id' => ['nullable', 'uuid'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
        ]);

        $this->enrollmentService->satisfyRequirement($enrollment, $instance, $request->user(), $data);

        return back()->with('success', 'Requirement marked satisfied.');
    }

    public function waiveRequirement(Request $request, Enrollment $enrollment, EnrollmentRequirementInstance $instance)
    {
        $this->authorize('manageRequirements', $enrollment);

        $school = GetSchoolModel();
        if ($enrollment->school_id !== $school->id) {
            abort(404);
        }

        $data = $request->validate([
            'waiver_reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->enrollmentService->waiveRequirement(
            $enrollment,
            $instance,
            $request->user(),
            $data['waiver_reason']
        );

        return back()->with('success', 'Requirement waived.');
    }

    public function readiness(Enrollment $enrollment)
    {
        $this->authorize('view', $enrollment);

        $school = GetSchoolModel();
        if ($enrollment->school_id !== $school->id) {
            abort(404);
        }

        $enrollment->load('requirementInstances.definition');

        return response()->json(
            $this->enrollmentService->evaluateReadiness($enrollment)
        );
    }

    public function finalize(Request $request, Enrollment $enrollment)
    {
        $this->authorize('finalize', $enrollment);

        $school = GetSchoolModel();
        if ($enrollment->school_id !== $school->id) {
            abort(404);
        }

        try {
            $finalized = $this->enrollmentService->finalize($enrollment, $request->user());

            return redirect()
                ->route('enrollments.show', $finalized)
                ->with('success', 'Enrollment finalized. Student record created.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Enrollment finalize failed', [
                'enrollment_id' => $enrollment->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Unable to finalize enrollment.']);
        }
    }
}
