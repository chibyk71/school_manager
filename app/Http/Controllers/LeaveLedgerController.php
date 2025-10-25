<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveLedgerRequest;
use App\Http\Requests\UpdateLeaveLedgerRequest;
use App\Models\Academic\AcademicSession;
use App\Models\Configuration\LeaveType;
use App\Models\Employee\LeaveLedger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing LeaveLedger resources.
 */
class LeaveLedgerController extends Controller
{
    /**
     * Display a listing of leave ledger entries with dynamic querying.
     *
     * @param Request $request
     * @param AcademicSession|null $academicSession
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request, ?AcademicSession $academicSession = null)
    {
        Gate::authorize('viewAny', LeaveLedger::class); // Policy-based authorization

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
                    'field' => 'academic_session_name',
                    'relation' => 'academicSession',
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
            ];

            // Build query
            $query = LeaveLedger::with([
                'leaveType:id,name',
                'academicSession:id,name',
                'user:id,first_name,last_name',
            ])->when($academicSession, fn($q) => $q->forAcademicSession($academicSession->id))
              ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query (search, filter, sort, paginate)
            $ledgerEntries = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($ledgerEntries);
            }

            return Inertia::render('HRM/LeaveLedgers', [
                'academicSession' => $academicSession ? $academicSession->only('id', 'name') : null,
                'ledgerEntries' => $ledgerEntries,
                'leaveTypes' => LeaveType::select('id', 'name')->get(),
                'academicSessions' => AcademicSession::select('id', 'name')->get(),
                'users' => User::select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch leave ledger entries: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch leave ledger entries'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch leave ledger entries']);
        }
    }

    /**
     * Show the form for creating a new leave ledger entry.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        Gate::authorize('create', LeaveLedger::class); // Policy-based authorization

        try {
            return Inertia::render('HRM/LeaveLedgerCreate', [
                'leaveTypes' => LeaveType::select('id', 'name')->get(),
                'academicSessions' => AcademicSession::select('id', 'name')->get(),
                'users' => User::select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load create form: ' . $e->getMessage());
            return redirect()->back()->with(['error' => 'Failed to load create form']);
        }
    }

    /**
     * Store a newly created leave ledger entry in storage.
     *
     * @param StoreLeaveLedgerRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreLeaveLedgerRequest $request)
    {
        Gate::authorize('create', LeaveLedger::class); // Policy-based authorization

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id; // Ensure school_id is set

            $ledgerEntry = LeaveLedger::create($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Leave ledger entry created successfully'], 201)
                : redirect()->route('leave-ledgers.index')->with(['success' => 'Leave ledger entry created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create leave ledger entry: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create leave ledger entry'], 500)
                : redirect()->back()->with(['error' => 'Failed to create leave ledger entry'])->withInput();
        }
    }

    /**
     * Display the specified leave ledger entry.
     *
     * @param Request $request
     * @param LeaveLedger $leaveLedger
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, LeaveLedger $leaveLedger)
    {
        Gate::authorize('view', $leaveLedger); // Policy-based authorization

        try {
            $leaveLedger->load([
                'leaveType:id,name',
                'academicSession:id,name',
                'user:id,first_name,last_name',
            ]);
            return response()->json(['leave_ledger' => $leaveLedger]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch leave ledger entry: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch leave ledger entry'], 500);
        }
    }

    /**
     * Show the form for editing the specified leave ledger entry.
     *
     * @param LeaveLedger $leaveLedger
     * @return \Inertia\Response
     */
    public function edit(LeaveLedger $leaveLedger)
    {
        Gate::authorize('update', $leaveLedger); // Policy-based authorization

        try {
            $leaveLedger->load([
                'leaveType:id,name',
                'academicSession:id,name',
                'user:id,first_name,last_name',
            ]);
            return Inertia::render('HRM/LeaveLedgerEdit', [
                'leaveLedger' => $leaveLedger,
                'leaveTypes' => LeaveType::select('id', 'name')->get(),
                'academicSessions' => AcademicSession::select('id', 'name')->get(),
                'users' => User::select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load edit form: ' . $e->getMessage());
            return redirect()->back()->with(['error' => 'Failed to load edit form']);
        }
    }

    /**
     * Update the specified leave ledger entry in storage.
     *
     * @param UpdateLeaveLedgerRequest $request
     * @param LeaveLedger $leaveLedger
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateLeaveLedgerRequest $request, LeaveLedger $leaveLedger)
    {
        Gate::authorize('update', $leaveLedger); // Policy-based authorization

        try {
            $validated = $request->validated();
            $leaveLedger->update($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Leave ledger entry updated successfully'])
                : redirect()->route('leave-ledgers.index')->with(['success' => 'Leave ledger entry updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update leave ledger entry: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update leave ledger entry'], 500)
                : redirect()->back()->with(['error' => 'Failed to update leave ledger entry'])->withInput();
        }
    }

    /**
     * Remove the specified leave ledger entry(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', LeaveLedger::class); // Policy-based authorization

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:leave_ledgers,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $deleted = $forceDelete
                ? LeaveLedger::whereIn('id', $ids)->forceDelete()
                : LeaveLedger::whereIn('id', $ids)->delete();

            $message = $deleted ? 'Leave ledger entry(s) deleted successfully' : 'No leave ledger entries were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('leave-ledgers.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete leave ledger entries: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete leave ledger entry(s)'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete leave ledger entry(s)']);
        }
    }

    /**
     * Restore a soft-deleted leave ledger entry.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $leaveLedger = LeaveLedger::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $leaveLedger); // Policy-based authorization

        try {
            $leaveLedger->restore();
            return response()->json(['message' => 'Leave ledger entry restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore leave ledger entry: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore leave ledger entry'], 500);
        }
    }
}