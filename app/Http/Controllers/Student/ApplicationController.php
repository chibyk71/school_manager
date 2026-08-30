<?php

namespace App\Http\Controllers\Student;

use App\Http\Requests\Student\SubmitApplicationRequest;
use App\Http\Resources\Student\StudentApplicationResource;
use App\Models\Student\StudentApplication;
use App\Services\Student\StudentApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Staff application management (Phase 2).
 * Approve ≠ Admit. No Student/Enrollment creation here.
 */
class ApplicationController
{
    public function __construct(
        protected StudentApplicationService $applicationService
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', StudentApplication::class);

        $applications = StudentApplication::with([
            'academicSession:id,name',
            'classLevel:id,name,display_name',
            'reviewer:id,name',
        ])
            ->tableQuery($request)
            ->paginate($request->integer('per_page', 20));

        return Inertia::render('Applications/Index', [
            'applications' => StudentApplicationResource::collection($applications),
            'filters' => $request->only(['search', 'status', 'source', 'academic_session_id']),
            'applicationsRequired' => $this->applicationService->applicationsRequired(),
            'feeConfig' => $this->applicationService->applicationFeeConfig(),
        ]);
    }

    public function show(StudentApplication $application)
    {
        Gate::authorize('view', $application);

        $application->load([
            'academicSession:id,name',
            'classLevel:id,name,display_name',
            'reviewer:id,name',
            'admissions',
            'customFieldResponses.customField',
        ]);

        $duplicates = $application->findLikelyDuplicates();

        return Inertia::render('Applications/Show', [
            'application' => new StudentApplicationResource($application),
            'possibleDuplicates' => StudentApplicationResource::collection($duplicates),
            'canReview' => Gate::allows('review', $application),
            'canApprove' => Gate::allows('approve', $application),
            'canReject' => Gate::allows('reject', $application),
        ]);
    }

    public function store(SubmitApplicationRequest $request)
    {
        Gate::authorize('create', StudentApplication::class);

        $school = GetSchoolModel();
        $application = $this->applicationService->submitStaffApplication(
            $request->validatedData(),
            $school,
            $request->user()
        );

        return redirect()
            ->route('applications.show', $application)
            ->with('success', 'Application created: '.$application->application_number);
    }

    public function beginReview(StudentApplication $application)
    {
        Gate::authorize('review', $application);

        try {
            $this->applicationService->beginReview($application, auth()->user());

            return back()->with('success', 'Application marked under review.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function approve(Request $request, StudentApplication $application)
    {
        Gate::authorize('approve', $application);

        $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->applicationService->approveApplication(
                $application,
                auth()->user(),
                $request->input('admin_notes')
            );

            return back()->with('success', 'Application approved. Candidate is not yet admitted or enrolled.');
        } catch (\Throwable $e) {
            Log::error('Application approve failed', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function reject(Request $request, StudentApplication $application)
    {
        Gate::authorize('reject', $application);

        $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $this->applicationService->rejectApplication(
                $application,
                $request->string('rejection_reason'),
                auth()->user()
            );

            return redirect()
                ->route('applications.index')
                ->with('success', 'Application rejected.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy(StudentApplication $application)
    {
        Gate::authorize('delete', $application);
        $application->delete();

        return redirect()
            ->route('applications.index')
            ->with('success', 'Application moved to trash.');
    }
}
