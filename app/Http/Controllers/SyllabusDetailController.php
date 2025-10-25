<?php

namespace App\Http\Controllers;

use App\Models\Resource\Syllabus;
use App\Models\Resource\SyllabusDetail;
use App\Models\Resource\SyllabusDetailApproval;
use App\Models\Employee\Staff;
use App\Notifications\SyllabusDetailAction;
use App\Notifications\SyllabusDetailApprovalAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Controller for managing syllabus details in the school management system.
 *
 * Handles CRUD operations, including soft delete, force delete, restore, and approval workflows,
 * for syllabus details, ensuring proper authorization, validation, school scoping,
 * and notifications for a multi-tenant SaaS environment.
 *
 * @package App\Http\Controllers
 */
class SyllabusDetailController extends Controller
{
    /**
     * Display a listing of syllabus details with search, filter, sort, and pagination.
     *
     * Uses the HasTableQuery trait to handle dynamic querying.
     * Renders the Academic/SyllabusDetails Vue component or returns JSON for API requests.
     *
     * @param Request $request The HTTP request containing query parameters.
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     * @throws \Exception If query fails or no active school is found.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', SyllabusDetail::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Define extra fields for table query
            $extraFields = [
                'syllabus' => ['field' => 'syllabus.topic', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
            ];

            // Build query
            $query = SyllabusDetail::with([
                'syllabus:id,topic',
                'latestApproval' => function ($query) {
                    $query->select('id', 'syllabus_detail_id', 'status', 'comments');
                },
            ])->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $syllabusDetails = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($syllabusDetails);
            }

            return Inertia::render('Academic/SyllabusDetails', [
                'syllabusDetails' => $syllabusDetails,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'syllabi' => Syllabus::where('school_id', $school->id)->select('id', 'topic')->get(),
                'approvers' => Staff::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'head_teacher']))
                    ->select('id', 'first_name', 'last_name')
                    ->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch syllabus details: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch syllabus details'], 500)
                : redirect()->back()->with('error', 'Failed to load syllabus details.');
        }
    }

    /**
     * Show the form for creating a new syllabus detail.
     *
     * Renders the Academic/SyllabusDetailCreate Vue component.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        $this->authorize('create', SyllabusDetail::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            return Inertia::render('Academic/SyllabusDetailCreate', [
                'syllabi' => Syllabus::where('school_id', $school->id)->select('id', 'topic')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load syllabus detail creation form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load syllabus detail creation form.');
        }
    }

    /**
     * Store a newly created syllabus detail in storage.
     *
     * Validates the input, creates the syllabus detail, attaches media, and sends notifications.
     *
     * @param Request $request The HTTP request containing syllabus detail data.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If creation fails.
     */
    public function store(Request $request)
    {
        $this->authorize('create', SyllabusDetail::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'syllabus_id' => 'required|exists:syllabi,id,school_id,' . $school->id,
                'week' => 'required|integer|min:1',
                'objectives' => 'nullable|string',
                'topic' => 'required|string|max:255',
                'sub_topics' => 'nullable|array',
                'description' => 'nullable|string',
                'resources' => 'nullable|array',
                'status' => 'required|in:draft,pending_approval,published,rejected,archived',
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:pdf,jpg,png|max:2048',
            ])->validate();

            // Create the syllabus detail
            $syllabusDetail = SyllabusDetail::create([
                'school_id' => $school->id,
                'syllabus_id' => $validated['syllabus_id'],
                'week' => $validated['week'],
                'objectives' => $validated['objectives'],
                'topic' => $validated['topic'],
                'sub_topics' => $validated['sub_topics'],
                'description' => $validated['description'],
                'resources' => $validated['resources'],
                'status' => $validated['status'],
            ]);

            // Attach media if provided
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $syllabusDetail->addMedia($file)->toMediaCollection('syllabus_detail_files');
                }
            }

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new SyllabusDetailAction($syllabusDetail, 'created'));

            // If status is pending_approval, create an approval request
            if ($syllabusDetail->status === 'pending_approval') {
                $approval = SyllabusDetailApproval::create([
                    'school_id' => $school->id,
                    'syllabus_detail_id' => $syllabusDetail->id,
                    'requester_id' => Auth::user()->staff->id,
                    'status' => 'pending',
                ]);
                Notification::send($users, new SyllabusDetailApprovalAction($syllabusDetail, $approval, 'submitted'));
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Syllabus detail created successfully'], 201)
                : redirect()->route('syllabus-details.index')->with('success', 'Syllabus detail created successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to create syllabus detail: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create syllabus detail'], 500)
                : redirect()->back()->with('error', 'Failed to create syllabus detail.');
        }
    }

    /**
     * Display the specified syllabus detail.
     *
     * Loads the syllabus detail with related data and returns a JSON response.
     *
     * @param Request $request The HTTP request.
     * @param SyllabusDetail $syllabusDetail The syllabus detail to display.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If no active school is found or syllabus detail is not accessible.
     */
    public function show(Request $request, SyllabusDetail $syllabusDetail)
    {
        $this->authorize('view', $syllabusDetail);

        try {
            $school = GetSchoolModel();
            if (!$school || $syllabusDetail->school_id !== $school->id) {
                throw new \Exception('Syllabus detail not found or not accessible.');
            }

            $syllabusDetail->load([
                'syllabus',
                'approvals' => function ($query) {
                    $query->with(['requester', 'approver'])->orderBy('created_at', 'desc');
                },
                'media',
            ]);

            return response()->json(['syllabusDetail' => $syllabusDetail]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch syllabus detail: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch syllabus detail'], 500);
        }
    }

    /**
     * Show the form for editing the specified syllabus detail.
     *
     * Renders the Academic/SyllabusDetailEdit Vue component.
     *
     * @param SyllabusDetail $syllabusDetail The syllabus detail to edit.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found or syllabus detail is not accessible.
     */
    public function edit(SyllabusDetail $syllabusDetail)
    {
        $this->authorize('update', $syllabusDetail);

        try {
            $school = GetSchoolModel();
            if (!$school || $syllabusDetail->school_id !== $school->id) {
                throw new \Exception('Syllabus detail not found or not accessible.');
            }

            $syllabusDetail->load(['syllabus', 'approvals']);

            return Inertia::render('Academic/SyllabusDetailEdit', [
                'syllabusDetail' => $syllabusDetail,
                'syllabi' => Syllabus::where('school_id', $school->id)->select('id', 'topic')->get(),
                'approvers' => Staff::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'head_teacher']))
                    ->select('id', 'first_name', 'last_name')
                    ->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load syllabus detail edit form: ' . $e->getMessage());
            return redirect()->route('syllabus-details.index')->with('error', 'Failed to load syllabus detail edit form.');
        }
    }

    /**
     * Update the specified syllabus detail in storage.
     *
     * Validates the input, updates the syllabus detail, syncs media, and sends notifications.
     *
     * @param Request $request The HTTP request containing updated syllabus detail data.
     * @param SyllabusDetail $syllabusDetail The syllabus detail to update.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If update fails.
     */
    public function update(Request $request, SyllabusDetail $syllabusDetail)
    {
        $this->authorize('update', $syllabusDetail);

        try {
            $school = GetSchoolModel();
            if (!$school || $syllabusDetail->school_id !== $school->id) {
                throw new \Exception('Syllabus detail not found or not accessible.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'syllabus_id' => 'required|exists:syllabi,id,school_id,' . $school->id,
                'week' => 'required|integer|min:1',
                'objectives' => 'nullable|string',
                'topic' => 'required|string|max:255',
                'sub_topics' => 'nullable|array',
                'description' => 'nullable|string',
                'resources' => 'nullable|array',
                'status' => 'required|in:draft,pending_approval,published,rejected,archived',
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:pdf,jpg,png|max:2048',
            ])->validate();

            // Update the syllabus detail
            $syllabusDetail->update([
                'syllabus_id' => $validated['syllabus_id'],
                'week' => $validated['week'],
                'objectives' => $validated['objectives'],
                'topic' => $validated['topic'],
                'sub_topics' => $validated['sub_topics'],
                'description' => $validated['description'],
                'resources' => $validated['resources'],
                'status' => $validated['status'],
            ]);

            // Sync media if provided
            if ($request->hasFile('media')) {
                $syllabusDetail->clearMediaCollection('syllabus_detail_files');
                foreach ($request->file('media') as $file) {
                    $syllabusDetail->addMedia($file)->toMediaCollection('syllabus_detail_files');
                }
            }

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new SyllabusDetailAction($syllabusDetail, 'updated'));

            // If status is pending_approval, create an approval request
            if ($syllabusDetail->status === 'pending_approval') {
                $approval = SyllabusDetailApproval::create([
                    'school_id' => $school->id,
                    'syllabus_detail_id' => $syllabusDetail->id,
                    'requester_id' => Auth::user()->staff->id,
                    'status' => 'pending',
                ]);
                Notification::send($users, new SyllabusDetailApprovalAction($syllabusDetail, $approval, 'submitted'));
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Syllabus detail updated successfully'])
                : redirect()->route('syllabus-details.index')->with('success', 'Syllabus detail updated successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update syllabus detail: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update syllabus detail'], 500)
                : redirect()->back()->with('error', 'Failed to update syllabus detail.');
        }
    }

    /**
     * Remove one or more syllabus details from storage (soft or force delete).
     *
     * Accepts an array of syllabus detail IDs via JSON request, performs soft or force delete,
     * and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of syllabus detail IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If deletion fails or syllabus details are not accessible.
     */
    public function destroy(Request $request)
    {
        $this->authorize('delete', SyllabusDetail::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:syllabus_details,id,school_id,' . $school->id,
                'force' => 'sometimes|boolean',
            ])->validate();

            // Notify before deletion
            $syllabusDetails = SyllabusDetail::whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            foreach ($syllabusDetails as $syllabusDetail) {
                Notification::send($users, new SyllabusDetailAction($syllabusDetail, 'deleted'));
            }

            // Perform soft or force delete
            $forceDelete = $request->boolean('force');
            $query = SyllabusDetail::whereIn('id', $validated['ids'])->where('school_id', $school->id);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? "$deleted syllabus detail(s) deleted successfully" : "No syllabus details were deleted";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('syllabus-details.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to delete syllabus details: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete syllabus detail(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete syllabus detail(s).');
        }
    }

    /**
     * Restore one or more soft-deleted syllabus details.
     *
     * Accepts an array of syllabus detail IDs via JSON request, restores them, and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of syllabus detail IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If restoration fails or syllabus details are not accessible.
     */
    public function restore(Request $request)
    {
        $this->authorize('restore', SyllabusDetail::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:syllabus_details,id,school_id,' . $school->id,
            ])->validate();

            // Notify before restoration
            $syllabusDetails = SyllabusDetail::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            foreach ($syllabusDetails as $syllabusDetail) {
                Notification::send($users, new SyllabusDetailAction($syllabusDetail, 'restored'));
            }

            // Restore the syllabus details
            $count = SyllabusDetail::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->restore();

            $message = $count ? "$count syllabus detail(s) restored successfully" : "No syllabus details were restored";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('syllabus-details.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to restore syllabus details: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to restore syllabus detail(s)'], 500)
                : redirect()->back()->with('error', 'Failed to restore syllabus detail(s).');
        }
    }

    /**
     * Submit a syllabus detail for approval.
     *
     * Creates an approval request and updates the syllabus detail status to pending_approval.
     *
     * @param Request $request The HTTP request.
     * @param SyllabusDetail $syllabusDetail The syllabus detail to submit for approval.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If submission fails.
     */
    public function submitApproval(Request $request, SyllabusDetail $syllabusDetail)
    {
        $this->authorize('submitApproval', $syllabusDetail);

        try {
            $school = GetSchoolModel();
            if (!$school || $syllabusDetail->school_id !== $school->id) {
                throw new \Exception('Syllabus detail not found or not accessible.');
            }

            // Validate that the syllabus detail can be submitted
            if (!in_array($syllabusDetail->status, ['draft', 'rejected'])) {
                throw new ValidationException(Validator::make([], [
                    'status' => 'The syllabus detail must be in draft or rejected status to submit for approval.',
                ]));
            }

            // Update status to pending_approval
            $syllabusDetail->update(['status' => 'pending_approval']);

            // Create approval request
            $approval = SyllabusDetailApproval::create([
                'school_id' => $school->id,
                'syllabus_detail_id' => $syllabusDetail->id,
                'requester_id' => Auth::user()->staff->id,
                'status' => 'pending',
            ]);

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'head_teacher']))
                ->get();
            Notification::send($users, new SyllabusDetailApprovalAction($syllabusDetail, $approval, 'submitted'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Syllabus detail submitted for approval successfully'])
                : redirect()->route('syllabus-details.index')->with('success', 'Syllabus detail submitted for approval successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to submit syllabus detail for approval: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to submit syllabus detail for approval'], 500)
                : redirect()->back()->with('error', 'Failed to submit syllabus detail for approval.');
        }
    }

    /**
     * Approve a syllabus detail.
     *
     * Updates the approval request and sets the syllabus detail status to published.
     *
     * @param Request $request The HTTP request containing approval comments.
     * @param SyllabusDetail $syllabusDetail The syllabus detail to approve.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If approval fails.
     */
    public function approve(Request $request, SyllabusDetail $syllabusDetail)
    {
        $this->authorize('approve', $syllabusDetail);

        try {
            $school = GetSchoolModel();
            if (!$school || $syllabusDetail->school_id !== $school->id) {
                throw new \Exception('Syllabus detail not found or not accessible.');
            }

            // Validate that the syllabus detail is pending approval
            if ($syllabusDetail->status !== 'pending_approval') {
                throw new ValidationException(Validator::make([], [
                    'status' => 'The syllabus detail must be pending approval to be approved.',
                ]));
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'comments' => 'nullable|string',
            ])->validate();

            // Update the latest approval request
            $approval = $syllabusDetail->latestApproval;
            if (!$approval || $approval->status !== 'pending') {
                throw new \Exception('No pending approval request found.');
            }

            $approval->update([
                'approver_id' => Auth::user()->staff->id,
                'status' => 'approved',
                'comments' => $validated['comments'],
            ]);

            // Update syllabus detail status
            $syllabusDetail->update(['status' => 'published']);

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new SyllabusDetailApprovalAction($syllabusDetail, $approval, 'approved'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Syllabus detail approved successfully'])
                : redirect()->route('syllabus-details.index')->with('success', 'Syllabus detail approved successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to approve syllabus detail: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to approve syllabus detail'], 500)
                : redirect()->back()->with('error', 'Failed to approve syllabus detail.');
        }
    }

    /**
     * Reject a syllabus detail.
     *
     * Updates the approval request and sets the syllabus detail status to rejected.
     *
     * @param Request $request The HTTP request containing rejection comments.
     * @param SyllabusDetail $syllabusDetail The syllabus detail to reject.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If rejection fails.
     */
    public function reject(Request $request, SyllabusDetail $syllabusDetail)
    {
        $this->authorize('reject', $syllabusDetail);

        try {
            $school = GetSchoolModel();
            if (!$school || $syllabusDetail->school_id !== $school->id) {
                throw new \Exception('Syllabus detail not found or not accessible.');
            }

            // Validate that the syllabus detail is pending approval
            if ($syllabusDetail->status !== 'pending_approval') {
                throw new ValidationException(Validator::make([], [
                    'status' => 'The syllabus detail must be pending approval to be rejected.',
                ]));
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'comments' => 'required|string',
            ])->validate();

            // Update the latest approval request
            $approval = $syllabusDetail->latestApproval;
            if (!$approval || $approval->status !== 'pending') {
                throw new \Exception('No pending approval request found.');
            }

            $approval->update([
                'approver_id' => Auth::user()->staff->id,
                'status' => 'rejected',
                'comments' => $validated['comments'],
            ]);

            // Update syllabus detail status
            $syllabusDetail->update(['status' => 'rejected']);

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new SyllabusDetailApprovalAction($syllabusDetail, $approval, 'rejected'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Syllabus detail rejected successfully'])
                : redirect()->route('syllabus-details.index')->with('success', 'Syllabus detail rejected successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to reject syllabus detail: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to reject syllabus detail'], 500)
                : redirect()->back()->with('error', 'Failed to reject syllabus detail.');
        }
    }
}
