<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveTypeRequest;
use App\Http\Requests\UpdateLeaveTypeRequest;
use App\Models\Configuration\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing LeaveType resources.
 */
class LeaveTypeController extends Controller
{
    /**
     * Display a listing of leave types with dynamic querying.
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', LeaveType::class); // Policy-based authorization

        try {
            // Build query
            $query = LeaveType::withCount(['leaveRequests', 'leaveAllocations'])
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query (search, filter, sort, paginate)
            $leaveTypes = $query->tableQuery($request);

            if ($request->wantsJson()) {
                return response()->json($leaveTypes);
            }

            return Inertia::render('HRM/LeaveTypes', [
                'leaveTypes' => $leaveTypes,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch leave types: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch leave types'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch leave types']);
        }
    }

    /**
     * Show the form for creating a new leave type.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        Gate::authorize('create', LeaveType::class); // Policy-based authorization

        try {
            return Inertia::render('HRM/LeaveTypeCreate');
        } catch (\Exception $e) {
            Log::error('Failed to load create form: ' . $e->getMessage());
            return redirect()->back()->with(['error' => 'Failed to load create form']);
        }
    }

    /**
     * Store a newly created leave type in storage.
     *
     * @param StoreLeaveTypeRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreLeaveTypeRequest $request)
    {
        Gate::authorize('create', LeaveType::class); // Policy-based authorization

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id; // Ensure school_id is set

            $leaveType = LeaveType::create($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Leave type created successfully'], 201)
                : redirect()->route('leave-types.index')->with(['success' => 'Leave type created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create leave type: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create leave type'], 500)
                : redirect()->back()->with(['error' => 'Failed to create leave type'])->withInput();
        }
    }

    /**
     * Display the specified leave type.
     *
     * @param Request $request
     * @param LeaveType $leaveType
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, LeaveType $leaveType)
    {
        Gate::authorize('view', $leaveType); // Policy-based authorization

        try {
            $leaveType->loadCount(['leaveRequests', 'leaveAllocations']);
            return response()->json(['leave_type' => $leaveType]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch leave type: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch leave type'], 500);
        }
    }

    /**
     * Show the form for editing the specified leave type.
     *
     * @param LeaveType $leaveType
     * @return \Inertia\Response
     */
    public function edit(LeaveType $leaveType)
    {
        Gate::authorize('update', $leaveType); // Policy-based authorization

        try {
            $leaveType->loadCount(['leaveRequests', 'leaveAllocations']);
            return Inertia::render('HRM/LeaveTypeEdit', [
                'leaveType' => $leaveType,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load edit form: ' . $e->getMessage());
            return redirect()->back()->with(['error' => 'Failed to load edit form']);
        }
    }

    /**
     * Update the specified leave type in storage.
     *
     * @param UpdateLeaveTypeRequest $request
     * @param LeaveType $leaveType
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType)
    {
        Gate::authorize('update', $leaveType); // Policy-based authorization

        try {
            $validated = $request->validated();
            $leaveType->update($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Leave type updated successfully'])
                : redirect()->route('leave-types.index')->with(['success' => 'Leave type updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update leave type: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update leave type'], 500)
                : redirect()->back()->with(['error' => 'Failed to update leave type'])->withInput();
        }
    }

    /**
     * Remove the specified leave type(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', LeaveType::class); // Policy-based authorization

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:leave_types,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $deleted = $forceDelete
                ? LeaveType::whereIn('id', $ids)->forceDelete()
                : LeaveType::whereIn('id', $ids)->delete();

            $message = $deleted ? 'Leave type(s) deleted successfully' : 'No leave types were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('leave-types.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete leave types: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete leave type(s)'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete leave type(s)']);
        }
    }

    /**
     * Restore a soft-deleted leave type.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $leaveType = LeaveType::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $leaveType); // Policy-based authorization

        try {
            $leaveType->restore();
            return response()->json(['message' => 'Leave type restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore leave type: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore leave type'], 500);
        }
    }
}