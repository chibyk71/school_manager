<?php

namespace App\Http\Controllers\Student;

use App\Http\Requests\Student\SubmitApplicationRequest;
use App\Http\Resources\Student\StudentApplicationResource;
use App\Models\Academic\AcademicSession;
use App\Models\Student\StudentApplication;
use App\Services\Student\StudentApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Public unauthenticated application flow (Phase 2).
 * Does not create Profile/User/Student/Admission/Enrollment.
 */
class PublicApplicationController
{
    public function __construct(
        protected StudentApplicationService $applicationService
    ) {}

    public function show(Request $request)
    {
        $school = GetSchoolModel();

        $sessions = AcademicSession::query()
            ->when($school, fn ($q) => $q->where('school_id', $school->id))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'name', 'school_id']);

        return Inertia::render('Public/Apply/Index', [
            'schoolName' => $school?->name,
            'sessions' => $sessions,
            'feeConfig' => $this->applicationService->applicationFeeConfig($school),
            'applicationsRequired' => $this->applicationService->applicationsRequired($school),
        ]);
    }

    public function store(SubmitApplicationRequest $request)
    {
        $school = GetSchoolModel();

        if (! $school) {
            return back()->withErrors(['error' => 'School context is required.'])->withInput();
        }

        try {
            $application = $this->applicationService->submitPublicApplication(
                $request->validatedData(),
                $school
            );

            return Inertia::render('Public/Apply/Submitted', [
                'applicationNumber' => $application->application_number,
                'applicationToken' => $application->application_token,
                'applicantName' => $application->full_name,
                'feeConfig' => $this->applicationService->applicationFeeConfig($school),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            Log::error('Public application submission failed', [
                'school_id' => $school->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withErrors(['error' => 'Unable to submit your application. Please try again.'])
                ->withInput();
        }
    }

    public function status(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string', 'min:32'],
        ]);

        $application = StudentApplication::query()
            ->where('application_token', $request->string('token'))
            ->first();

        return Inertia::render('Public/Apply/Status', [
            'found' => $application !== null,
            'application' => $application
                ? new StudentApplicationResource($application)
                : null,
        ]);
    }
}
