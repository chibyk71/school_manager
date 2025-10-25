<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveRequestRequest;
use App\Http\Requests\UpdateLeaveRequestRequest;
use App\Models\Configuration\LeaveType;
use App\Models\Employee\LeaveAllocation;
use App\Models\Employee\LeaveLedger;
use App\Models\Employee\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Controller for managing LeaveRequest resources.
 */
class LeaveRequestController extends Controller
{
    /**
     * Display a listing of leave requests with dynamic querying.
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', LeaveRequest::class); // Policy-based authorization

        try {
            // Define extra fields for table query
            $extraFields = [
                [
                    'field' => 'leave_type_name',
                    'relation' => 'leaveType',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'user_name',
                    'relation' => 'user',
                    'relatedField' => 'full_name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'approver_name',
                    'relation' => 'approvedBy',
                    'relatedField' => 'full_name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'rejector_name',
                    'relation' => 'rejectedBy',
                    'relatedField' => 'full_name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            // Build query
            $query = LeaveRequest::with([
                'leaveType:id,name',
                'user:id,first_name,last_name',
                'approvedBy:id,first_name,last_name',
                'rejectedBy:id,first_name,last_name',
            ])->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query (search, filter, sort, paginate)
            $leaveRequests = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($leaveRequests);
            }

            return Inertia::render('HRM/LeaveRequests', [
                'leaveRequests' => $leaveRequests,
                'leaveTypes' => LeaveType::select('id', 'name')->get(),
                'users' => User::select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch leave requests: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch leave requests'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch leave requests']);
        }
    }

    /**
     * Show the form for creating a new leave request.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        Gate::authorize('create', LeaveRequest::class); // Policy-based authorization

        try {
            return Inertia::render('HRM/LeaveRequestCreate', [
                'leaveTypes' => LeaveType::select('id', 'name')->get(),
                'users' => User::select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load create form: ' . $e->getMessage());
            return redirect()->back()->with(['error' => 'Failed to load create form']);
        }
    }

    /**
     * Store a newly created leave request in storage.
     *
     * @param StoreLeaveRequestRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreLeaveRequestRequest $request)
    {
        Gate::authorize('create', LeaveRequest::class); // Policy-based authorization

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id; // Ensure school_id is set
            $validated['status'] = 'pending'; // Default status

            // Validate leave balance
            $leaveAllocation = LeaveAllocation::where([
                'school_id' => $school->id,
                'user_id' => $validated['user_id'],
                'leave_type_id' => $validated['leave_type_id'],
            ])->firstOrFail();

            $daysRequested = $validated['start_date']->diffInDays($validated['end_date']) + 1;
            $usedDays = LeaveLedger::where([
                'school_id' => $school->id,
                'user_id' => $validated['user_id'],
                'leave_type_id' => $validated['leave_type_id'],
            ])->sum('encashed_days');

            if ($usedDays + $daysRequested > $leaveAllocation->no_of_days) {
                throw ValidationException::withMessages(['start_date' => 'Requested leave days exceed available balance']);
            }

            $leaveRequest = LeaveRequest::create($validated);

            $admins = User::whereRoleIs('admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new LeaveRequestStatusNotification($leaveRequest, 'pending'));
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Leave request created successfully'], 201)
                : redirect()->route('leave-requests.index')->with(['success' => 'Leave request created successfully']);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['error' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to create leave request: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create leave request'], 500)
                : redirect()->back()->with(['error' => 'Failed to create leave request'])->withInput();
        }
    }

    /**
     * Display the specified leave request.
     *
     * @param Request $request
     * @param LeaveRequest $leaveRequest
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, LeaveRequest $leaveRequest)
    {
        Gate::authorize('view', $leaveRequest); // Policy-based authorization

        try {
            $leaveRequest->load([
                'leaveType:id,name',
                'user:id,first_name,last_name',
                'approvedBy:id,first_name,last_name',
                'rejectedBy:id,first_name,last_name',
            ]);
            return response()->json(['leave_request' => $leaveRequest]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch leave request: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch leave request'], 500);
        }
    }

    /**
     * Show the form for editing the specified leave request.
     *
     * @param LeaveRequest $leaveRequest
     * @return \Inertia\Response
     */
    public function edit(LeaveRequest $leaveRequest)
    {
        Gate::authorize('update', $leaveRequest); // Policy-based authorization

        try {
            $leaveRequest->load([
                'leaveType:id,name',
                'user:id,first_name,last_name',
                'approvedBy:id,first_name,last_name',
                'rejectedBy:id,first_name,last_name',
            ]);
            return Inertia::render('HRM/LeaveRequestEdit', [
                'leaveRequest' => $leaveRequest,
                'leaveTypes' => LeaveType::select('id', 'name')->get(),
                'users' => User::select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load edit form: ' . $e->getMessage());
            return redirect()->back()->with(['error' => 'Failed to load edit form']);
        }
    }

    /**
     * Update the specified leave request in storage.
     *
     * @param UpdateLeaveRequestRequest $request
     * @param LeaveRequest $leaveRequest
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateLeaveRequestRequest $request, LeaveRequest $leaveRequest)
    {
        Gate::authorize('update', $leaveRequest); // Policy-based authorization

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();

            // Validate leave balance if dates or leave type are updated
            if (isset($validated['start_date'], $validated['end_date'], $validated['leave_type_id'])) {
                $leaveAllocation = LeaveAllocation::where([
                    'school_id' => $school->id,
                    'user_id' => $leaveRequest->user_id,
                    'leave_type_id' => $validated['leave_type_id'],
                ])->firstOrFail();

                $daysRequested = $validated['start_date']->diffInDays($validated['end_date']) + 1;
                $usedDays = LeaveLedger::where([
                    'school_id' => $school->id,
                    'user_id' => $leaveRequest->user_id,
                    'leave_type_id' => $validated['leave_type_id'],
                ])->sum('encashed_days');

                if ($usedDays + $daysRequested > $leaveAllocation->no_of_days) {
                    throw ValidationException::withMessages(['start_date' => 'Requested leave days exceed available balance']);
                }
            }

            $leaveRequest->update($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Leave request updated successfully'])
                : redirect()->route('leave-requests.index')->with(['success' => 'Leave request updated successfully']);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['error' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update leave request: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update leave request'], 500)
                : redirect()->back()->with(['error' => 'Failed to update leave request'])->withInput();
        }
    }

    /**
     * Remove the specified leave request(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', LeaveRequest::class); // Policy-based authorization

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:leave_requests,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $deleted = $forceDelete
                ? LeaveRequest::whereIn('id', $ids)->forceDelete()
                : LeaveRequest::whereIn('id', $ids)->delete();

            $message = $deleted ? 'Leave request(s) deleted successfully' : 'No leave requests were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('leave-requests.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete leave requests: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete leave request(s)'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete leave request(s)']);
        }
    }

    /**
     * Restore a soft-deleted leave request.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $leaveRequest = LeaveRequest::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $leaveRequest); // Policy-based authorization

        try {
            $leaveRequest->restore();
            return response()->json(['message' => 'Leave request restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore leave request: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore leave request'], 500);
        }
    }

    /**
     * Approve or reject a leave request.
     *
     * @param Request $request
     * @param LeaveRequest $leaveRequest
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function manageApproval(Request $request, LeaveRequest $leaveRequest)
    {
        Gate::authorize('manageApproval', $leaveRequest); // Policy-based authorization

        try {
            $request->validate([
                'status' => 'required|in:approved,rejected',
                'rejected_reason' => 'required_if:status,rejected|string|nullable',
            ]);

            $school = GetSchoolModel();
            $user = auth()->user();

            DB::transaction(function () use ($request, $leaveRequest, $user, $school) {
                $status = $request->input('status');
                $leaveRequest->update([
                    'status' => $status,
                    'approved_by' => $status === 'approved' ? $user->id : null,
                    'approved_at' => $status === 'approved' ? now() : null,
                    'rejected_by' => $status === 'rejected' ? $user->id : null,
                    'rejected_at' => $status === 'rejected' ? now() : null,
                    'rejected_reason' => $status === 'rejected' ? $request->input('rejected_reason') : null,
                ]);

                // Update LeaveLedger if approved
                if ($status === 'approved') {
                    $daysRequested = $leaveRequest->start_date->diffInDays($leaveRequest->end_date) + 1;
                    LeaveLedger::create([
                        'school_id' => $school->id,
                        'user_id' => $leaveRequest->user_id,
                        'leave_type_id' => $leaveRequest->leave_type_id,
                        'academic_session_id' => $leaveRequest->academicSession->id,
                        'encashed_days' => $daysRequested,
                    ]);
                }

                // Send notification
                $leaveRequest->user->notify(new LeaveRequestStatusNotification($leaveRequest, $status, $request->input('rejected_reason')));
            });

            return $request->wantsJson()
                ? response()->json(['message' => "Leave request {$request->status} successfully"])
                : redirect()->route('leave-requests.index')->with(['success' => "Leave request {$request->status} successfully"]);
        } catch (\Exception $e) {
            Log::error('Failed to manage leave request approval: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to manage leave request approval'], 500)
                : redirect()->back()->with(['error' => 'Failed to manage leave request approval']);
        }
    }
}