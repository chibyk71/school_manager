<?php

namespace App\Http\Controllers\Student;

use App\Http\Requests\Student\AdmitApplicationRequest;
use App\Http\Resources\Student\StudentApplicationResource;
use App\Models\Student\StudentApplication;
use App\Services\Student\StudentApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * ApplicationController – Admin Management of Student Applications
 *
 * Handles all administrative operations on student applications:
 *   - Listing / filtering applications (DataTable-ready via HasTableQuery)
 *   - Viewing detailed application information
 *   - Admitting applications → creates an enrolled Student record
 *   - Rejecting applications with mandatory reason
 *   - Soft-deleting and restoring applications
 *
 * ── Authorization ─────────────────────────────────────────────────────────────
 * Every action is gated through StudentApplicationPolicy. The policy handles:
 *   - Permission checks (applications.view, admit, reject, delete, restore)
 *   - Multi-tenant school scoping (cross-tenant access is blocked at policy level)
 *   - Super-admin bypass via the before() hook
 *
 * ── Separation of Concerns ───────────────────────────────────────────────────
 * This controller is intentionally thin. All business logic (state transitions,
 * student creation, event dispatch, notifications) lives in StudentApplicationService.
 *
 * ── Fits into the Student Management Module ──────────────────────────────────
 * - Route prefix: /admin/applications
 * - Works with frontend: Students/Applications/Index.vue, Applications/Show.vue
 * - Pairs with PublicApplicationController (public-facing submission flow)
 * - Policy: App\Policies\Student\StudentApplicationPolicy
 *
 * ── Registration ─────────────────────────────────────────────────────────────
 * Register the policy in AuthServiceProvider:
 *   StudentApplication::class => StudentApplicationPolicy::class
 */
class ApplicationController
{
    public function __construct(
        protected StudentApplicationService $applicationService
    ) {
    }

    /**
     * List all student applications with DataTable support.
     * GET /admin/applications
     *
     * Supports: search, status filter, source filter, pagination.
     * Uses HasTableQuery trait on StudentApplication model.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', StudentApplication::class);

        $applications = StudentApplication::with([
            'admissionSession',
            'assignedClassSection.classLevel',
            'reviewer:id,name',
        ])
            ->tableQuery($request)
            ->paginate($request->integer('per_page', 20));

        return Inertia::render('Students/Applications/Index', [
            'applications' => StudentApplicationResource::collection($applications),
            'filters' => $request->only(['search', 'status', 'entry_class']),
        ]);
    }

    /**
     * Show a single application with full details.
     * GET /admin/applications/{application}
     */
    public function show(StudentApplication $application)
    {
        Gate::authorize('view', $application);

        $application->load([
            'admissionSession',
            'assignedClassSection.classLevel',
            'examResult',
            'payments',
            'student.profile',
        ]);

        return Inertia::render('Students/Applications/Show', [
            'application' => new StudentApplicationResource($application),
        ]);
    }

    /**
     * Admit a pending application — converts it to an enrolled Student record.
     * POST /admin/applications/{application}/admit
     *
     * The service handles: state guard, student creation, event dispatch,
     * guardian notifications, and audit logging.
     */
    public function admit(AdmitApplicationRequest $request, StudentApplication $application)
    {
        Gate::authorize('admit', $application);

        try {
            $student = $this->applicationService->admitApplication(
                application: $application,
                placementData: $request->validated('placement', []),
                admin: auth()->user(),
            );

            return redirect()
                ->route('admin.students.show', $student)
                ->with('success', 'Application admitted. Student record created successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to admit application', [
                'application_id' => $application->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Reject a pending application with a mandatory reason.
     * POST /admin/applications/{application}/reject
     */
    public function reject(Request $request, StudentApplication $application)
    {
        Gate::authorize('reject', $application);

        $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        try {
            $this->applicationService->rejectApplication(
                application: $application,
                reason: $request->string('rejection_reason'),
                admin: auth()->user(),
            );

            return redirect()
                ->route('admin.applications.index')
                ->with('success', 'Application rejected and applicant notified.');

        } catch (\Exception $e) {
            Log::error('Failed to reject application', [
                'application_id' => $application->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Soft-delete an application.
     * DELETE /admin/applications/{application}
     */
    public function destroy(StudentApplication $application)
    {
        Gate::authorize('delete', $application);

        $application->delete();

        return redirect()
            ->route('admin.applications.index')
            ->with('success', 'Application moved to trash.');
    }

    /**
     * Restore a soft-deleted application.
     * POST /admin/applications/{application}/restore
     */
    public function restore(string $id)
    {
        $application = StudentApplication::onlyTrashed()->findOrFail($id);

        Gate::authorize('restore', $application);

        $application->restore();

        return redirect()
            ->route('admin.applications.index')
            ->with('success', 'Application restored.');
    }
}