<?php

namespace App\Http\Controllers;

use App\Models\Resource\LessonPlan;
use App\Models\Resource\LessonPlanDetail;
use App\Models\Resource\LessonPlanDetailApproval;
use App\Models\Employee\Staff;
use App\Notifications\LessonPlanDetailAction;
use App\Notifications\LessonPlanDetailApprovalAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Controller for managing lesson plan details in the school management system.
 *
 * Handles CRUD operations, including soft delete, force delete, restore, and approval workflows,
 * for lesson plan details, ensuring proper authorization, validation, school scoping,
 * and notifications for a multi-tenant SaaS environment.
 *
 * @package App\Http\Controllers
 */
class LessonPlanDetailController extends Controller
{
    /**
     * Display a listing of lesson plan details with search, filter, sort, and pagination.
     *
     * Uses the HasTableQuery trait to handle dynamic querying.
     * Renders the Academic/LessonPlanDetails Vue component or returns JSON for API requests.
     *
     * @param Request $request The HTTP request containing query parameters.
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     * @throws \Exception If query fails or no active school is found.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', LessonPlanDetail::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Define extra fields for table query
            $extraFields = [
                'lessonPlan' => ['field' => 'lesson_plan.topic', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
            ];

            // Build query
            $query = LessonPlanDetail::with([
                'lessonPlan:id,topic',
                'latestApproval' => function ($query) {
                    $query->select('id', 'lesson_plan_detail_id', 'status', 'comments');
                },
            ])->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $lessonPlanDetails = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($lessonPlanDetails);
            }

            return Inertia::render('Academic/LessonPlanDetails', [
                'lessonPlanDetails' => $lessonPlanDetails,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'lessonPlans' => LessonPlan::where('school_id', $school->id)->select('id', 'topic')->get(),
                'approvers' => Staff::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'head_teacher']))
                    ->select('id', 'first_name', 'last_name')
                    ->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch lesson plan details: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch lesson plan details'], 500)
                : redirect()->back()->with('error', 'Failed to load lesson plan details.');
        }
    }

    /**
     * Show the form for creating a new lesson plan detail.
     *
     * Renders the Academic/LessonPlanDetailCreate Vue component.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        Gate::authorize('create', LessonPlanDetail::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            return Inertia::render('Academic/LessonPlanDetailCreate', [
                'lessonPlans' => LessonPlan::where('school_id', $school->id)->select('id', 'topic')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load lesson plan detail creation form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load lesson plan detail creation form.');
        }
    }

    /**
     * Store a newly created lesson plan detail in storage.
     *
     * Validates the input, creates the lesson plan detail, attaches media, and sends notifications.
     *
     * @param Request $request The HTTP request containing lesson plan detail data.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If creation fails.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', LessonPlanDetail::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'lesson_plan_id' => 'required|exists:lesson_plans,id,school_id,' . $school->id,
                'title' => 'required|string|max:255',
                'sub_title' => 'nullable|string|max:255',
                'objective' => 'nullable|string',
                'activity' => 'required|array|min:1',
                'teaching_method' => 'nullable|array',
                'evaluation' => 'nullable|array',
                'resources' => 'nullable|array',
                'duration' => 'required|integer|min:1',
                'remarks' => 'nullable|string',
                'status' => 'required|in:draft,pending_approval,published,rejected,archived',
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:pdf,jpg,png|max:2048',
            ])->validate();

            // Create the lesson plan detail
            $lessonPlanDetail = LessonPlanDetail::create([
                'school_id' => $school->id,
                'lesson_plan_id' => $validated['lesson_plan_id'],
                'title' => $validated['title'],
                'sub_title' => $validated['sub_title'],
                'objective' => $validated['objective'],
                'activity' => $validated['activity'],
                'teaching_method' => $validated['teaching_method'],
                'evaluation' => $validated['evaluation'],
                'resources' => $validated['resources'],
                'duration' => $validated['duration'],
                'remarks' => $validated['remarks'],
                'status' => $validated['status'],
            ]);

            // Attach media if provided
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $lessonPlanDetail->addMedia($file)->toMediaCollection('lesson_plan_detail_files');
                }
            }

            // Notify staff (admin/teacher roles)
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new LessonPlanDetailAction($lessonPlanDetail, 'created'));

            // If status is pending_approval, create an approval request
            if ($lessonPlanDetail->status === 'pending_approval') {
                $approval = LessonPlanDetailApproval::create([
                    'school_id' => $school->id,
                    'lesson_plan_detail_id' => $lessonPlanDetail->id,
                    'requester_id' => Auth::user()->staff->id,
                    'status' => 'pending',
                ]);
                Notification::send($users, new LessonPlanDetailApprovalAction($lessonPlanDetail, $approval, 'submitted'));
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Lesson plan detail created successfully'], 201)
                : redirect()->route('lesson-plan-details.index')->with('success', 'Lesson plan detail created successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to create lesson plan detail: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create lesson plan detail'], 500)
                : redirect()->back()->with('error', 'Failed to create lesson plan detail.');
        }
    }

    /**
     * Display the specified lesson plan detail.
     *
     * Loads the lesson plan detail with related data and returns a JSON response.
     *
     * @param Request $request The HTTP request.
     * @param LessonPlanDetail $lessonPlanDetail The lesson plan detail to display.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If no active school is found or lesson plan detail is not accessible.
     */
    public function show(Request $request, LessonPlanDetail $lessonPlanDetail)
    {
        Gate::authorize('view', $lessonPlanDetail);

        try {
            $school = GetSchoolModel();
            if (!$school || $lessonPlanDetail->school_id !== $school->id) {
                throw new \Exception('Lesson plan detail not found or not accessible.');
            }

            $lessonPlanDetail->load(['lessonPlan', 'approvals' => function ($query) {
                $query->with(['requester', 'approver'])->orderBy('created_at', 'desc');
            }, 'media']);

            return response()->json(['lesson_plan_detail' => $lessonPlanDetail]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch lesson plan detail: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch lesson plan detail'], 500);
        }
    }

    /**
     * Show the form for editing the specified lesson plan detail.
     *
     * Renders the Academic/LessonPlanDetailEdit Vue component.
     *
     * @param LessonPlanDetail $lessonPlanDetail The lesson plan detail to edit.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found or lesson plan detail is not accessible.
     */
    public function edit(LessonPlanDetail $lessonPlanDetail)
    {
        Gate::authorize('update', $lessonPlanDetail);

        try {
            $school = GetSchoolModel();
            if (!$school || $lessonPlanDetail->school_id !== $school->id) {
                throw new \Exception('Lesson plan detail not found or not accessible.');
            }

            $lessonPlanDetail->load(['lessonPlan', 'approvals']);

            return Inertia::render('Academic/LessonPlanDetailEdit', [
                'lessonPlanDetail' => $lessonPlanDetail,
                'lessonPlans' => LessonPlan::where('school_id', $school->id)->select('id', 'topic')->get(),
                'approvers' => Staff::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'head_teacher']))
                    ->select('id', 'first_name', 'last_name')
                    ->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load lesson plan detail edit form: ' . $e->getMessage());
            return redirect()->route('lesson-plan-details.index')->with('error', 'Failed to load lesson plan detail edit form.');
        }
    }

    /**
     * Update the specified lesson plan detail in storage.
     *
     * Validates the input, updates the lesson plan detail, syncs media, and sends notifications.
     *
     * @param Request $request The HTTP request containing updated lesson plan detail data.
     * @param LessonPlanDetail $lessonPlanDetail The lesson plan detail to update.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If update fails.
     */
    public function update(Request $request, LessonPlanDetail $lessonPlanDetail)
    {
        Gate::authorize('update', $lessonPlanDetail);

        try {
            $school = GetSchoolModel();
            if (!$school || $lessonPlanDetail->school_id !== $school->id) {
                throw new \Exception('Lesson plan detail not found or not accessible.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'lesson_plan_id' => 'required|exists:lesson_plans,id,school_id,' . $school->id,
                'title' => 'required|string|max:255',
                'sub_title' => 'nullable|string|max:255',
                'objective' => 'nullable|string',
                'activity' => 'required|array|min:1',
                'teaching_method' => 'nullable|array',
                'evaluation' => 'nullable|array',
                'resources' => 'nullable|array',
                'duration' => 'required|integer|min:1',
                'remarks' => 'nullable|string',
                'status' => 'required|in:draft,pending_approval,published,rejected,archived',
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:pdf,jpg,png|max:2048',
            ])->validate();

            // Update the lesson plan detail
            $lessonPlanDetail->update([
                'lesson_plan_id' => $validated['lesson_plan_id'],
                'title' => $validated['title'],
                'sub_title' => $validated['sub_title'],
                'objective' => $validated['objective'],
                'activity' => $validated['activity'],
                'teaching_method' => $validated['teaching_method'],
                'evaluation' => $validated['evaluation'],
                'resources' => $validated['resources'],
                'duration' => $validated['duration'],
                'remarks' => $validated['remarks'],
                'status' => $validated['status'],
            ]);

            // Sync media if provided
            if ($request->hasFile('media')) {
                $lessonPlanDetail->clearMediaCollection('lesson_plan_detail_files');
                foreach ($request->file('media') as $file) {
                    $lessonPlanDetail->addMedia($file)->toMediaCollection('lesson_plan_detail_files');
                }
            }

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new LessonPlanDetailAction($lessonPlanDetail, 'updated'));

            // If status is pending_approval, create an approval request
            if ($lessonPlanDetail->status === 'pending_approval') {
                $approval = LessonPlanDetailApproval::create([
                    'school_id' => $school->id,
                    'lesson_plan_detail_id' => $lessonPlanDetail->id,
                    'requester_id' => Auth::user()->staff->id,
                    'status' => 'pending',
                ]);
                Notification::send($users, new LessonPlanDetailApprovalAction($lessonPlanDetail, $approval, 'submitted'));
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Lesson plan detail updated successfully'])
                : redirect()->route('lesson-plan-details.index')->with('success', 'Lesson plan detail updated successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update lesson plan detail: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update lesson plan detail'], 500)
                : redirect()->back()->with('error', 'Failed to update lesson plan detail.');
        }
    }

    /**
     * Remove one or more lesson plan details from storage (soft or force delete).
     *
     * Accepts an array of lesson plan detail IDs via JSON request, performs soft or force delete,
     * and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of lesson plan detail IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If deletion fails or lesson plan details are not accessible.
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', LessonPlanDetail::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:lesson_plan_details,id,school_id,' . $school->id,
                'force' => 'sometimes|boolean',
            ])->validate();

            // Notify before deletion
            $lessonPlanDetails = LessonPlanDetail::whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            foreach ($lessonPlanDetails as $lessonPlanDetail) {
                Notification::send($users, new LessonPlanDetailAction($lessonPlanDetail, 'deleted'));
            }

            // Perform soft or force delete
            $forceDelete = $request->boolean('force');
            $query = LessonPlanDetail::whereIn('id', $validated['ids'])->where('school_id', $school->id);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? "$deleted lesson plan detail(s) deleted successfully" : "No lesson plan details were deleted";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('lesson-plan-details.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to delete lesson plan details: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete lesson plan detail(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete lesson plan detail(s).');
        }
    }

    /**
     * Restore one or more soft-deleted lesson plan details.
     *
     * Accepts an array of lesson plan detail IDs via JSON request, restores them, and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of lesson plan detail IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If restoration fails or lesson plan details are not accessible.
     */
    public function restore(Request $request)
    {
        Gate::authorize('restore', LessonPlanDetail::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:lesson_plan_details,id,school_id,' . $school->id,
            ])->validate();

            // Notify before restoration
            $lessonPlanDetails = LessonPlanDetail::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            foreach ($lessonPlanDetails as $lessonPlanDetail) {
                Notification::send($users, new LessonPlanDetailAction($lessonPlanDetail, 'restored'));
            }

            // Restore the lesson plan details
            $count = LessonPlanDetail::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->restore();

            $message = $count ? "$count lesson plan detail(s) restored successfully" : "No lesson plan details were restored";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('lesson-plan-details.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to restore lesson plan details: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to restore lesson plan detail(s)'], 500)
                : redirect()->back()->with('error', 'Failed to restore lesson plan detail(s).');
        }
    }

    /**
     * Submit a lesson plan detail for approval.
     *
     * Creates an approval request and updates the lesson plan detail status to pending_approval.
     *
     * @param Request $request The HTTP request.
     * @param LessonPlanDetail $lessonPlanDetail The lesson plan detail to submit for approval.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If submission fails.
     */
    public function submitApproval(Request $request, LessonPlanDetail $lessonPlanDetail)
    {
        Gate::authorize('submitApproval', $lessonPlanDetail);

        try {
            $school = GetSchoolModel();
            if (!$school || $lessonPlanDetail->school_id !== $school->id) {
                throw new \Exception('Lesson plan detail not found or not accessible.');
            }

            // Validate that the lesson plan detail can be submitted
            if (!in_array($lessonPlanDetail->status, ['draft', 'rejected'])) {
                throw new ValidationException(Validator::make([], [
                    'status' => 'The lesson plan detail must be in draft or rejected status to submit for approval.',
                ]));
            }

            // Update status to pending_approval
            $lessonPlanDetail->update(['status' => 'pending_approval']);

            // Create approval request
            $approval = LessonPlanDetailApproval::create([
                'school_id' => $school->id,
                'lesson_plan_detail_id' => $lessonPlanDetail->id,
                'requester_id' => Auth::user()->staff->id,
                'status' => 'pending',
            ]);

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'head_teacher']))
                ->get();
            Notification::send($users, new LessonPlanDetailApprovalAction($lessonPlanDetail, $approval, 'submitted'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Lesson plan detail submitted for approval successfully'])
                : redirect()->route('lesson-plan-details.index')->with('success', 'Lesson plan detail submitted for approval successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to submit lesson plan detail for approval: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to submit lesson plan detail for approval'], 500)
                : redirect()->back()->with('error', 'Failed to submit lesson plan detail for approval.');
        }
    }

    /**
     * Approve a lesson plan detail.
     *
     * Updates the approval request and sets the lesson plan detail status to published.
     *
     * @param Request $request The HTTP request containing approval comments.
     * @param LessonPlanDetail $lessonPlanDetail The lesson plan detail to approve.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If approval fails.
     */
    public function approve(Request $request, LessonPlanDetail $lessonPlanDetail)
    {
        Gate::authorize('approve', $lessonPlanDetail);

        try {
            $school = GetSchoolModel();
            if (!$school || $lessonPlanDetail->school_id !== $school->id) {
                throw new \Exception('Lesson plan detail not found or not accessible.');
            }

            // Validate that the lesson plan detail is pending approval
            if ($lessonPlanDetail->status !== 'pending_approval') {
                throw new ValidationException(Validator::make([], [
                    'status' => 'The lesson plan detail must be pending approval to be approved.',
                ]));
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'comments' => 'nullable|string',
            ])->validate();

            // Update the latest approval request
            $approval = $lessonPlanDetail->latestApproval;
            if (!$approval || $approval->status !== 'pending') {
                throw new \Exception('No pending approval request found.');
            }

            $approval->update([
                'approver_id' => Auth::user()->staff->id,
                'status' => 'approved',
                'comments' => $validated['comments'],
            ]);

            // Update lesson plan detail status
            $lessonPlanDetail->update(['status' => 'published']);

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new LessonPlanDetailApprovalAction($lessonPlanDetail, $approval, 'approved'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Lesson plan detail approved successfully'])
                : redirect()->route('lesson-plan-details.index')->with('success', 'Lesson plan detail approved successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to approve lesson plan detail: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to approve lesson plan detail'], 500)
                : redirect()->back()->with('error', 'Failed to approve lesson plan detail.');
        }
    }

    /**
     * Reject a lesson plan detail.
     *
     * Updates the approval request and sets the lesson plan detail status to rejected.
     *
     * @param Request $request The HTTP request containing rejection comments.
     * @param LessonPlanDetail $lessonPlanDetail The lesson plan detail to reject.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If rejection fails.
     */
    public function reject(Request $request, LessonPlanDetail $lessonPlanDetail)
    {
        Gate::authorize('reject', $lessonPlanDetail);

        try {
            $school = GetSchoolModel();
            if (!$school || $lessonPlanDetail->school_id !== $school->id) {
                throw new \Exception('Lesson plan detail not found or not accessible.');
            }

            // Validate that the lesson plan detail is pending approval
            if ($lessonPlanDetail->status !== 'pending_approval') {
                throw new ValidationException(Validator::make([], [
                    'status' => 'The lesson plan detail must be pending approval to be rejected.',
                ]));
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'comments' => 'required|string',
            ])->validate();

            // Update the latest approval request
            $approval = $lessonPlanDetail->latestApproval;
            if (!$approval || $approval->status !== 'pending') {
                throw new \Exception('No pending approval request found.');
            }

            $approval->update([
                'approver_id' => Auth::user()->staff->id,
                'status' => 'rejected',
                'comments' => $validated['comments'],
            ]);

            // Update lesson plan detail status
            $lessonPlanDetail->update(['status' => 'rejected']);

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new LessonPlanDetailApprovalAction($lessonPlanDetail, $approval, 'rejected'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Lesson plan detail rejected successfully'])
                : redirect()->route('lesson-plan-details.index')->with('success', 'Lesson plan detail rejected successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to reject lesson plan detail: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to reject lesson plan detail'], 500)
                : redirect()->back()->with('error', 'Failed to reject lesson plan detail.');
        }
    }
}
