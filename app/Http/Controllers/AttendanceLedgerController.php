<?php

namespace App\Http\Controllers;

use App\Models\Academic\Student;
use App\Models\Employee\Staff;
use App\Models\Misc\AttendanceLedger;
use App\Models\Misc\AttendanceSession;
use App\Notifications\AttendanceLedgerAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Controller for managing attendance ledgers in the school management system.
 *
 * Handles CRUD operations, including soft delete, force delete, and restore,
 * for attendance ledgers, ensuring proper authorization, validation, school scoping,
 * and notifications for a multi-tenant SaaS environment.
 *
 * @package App\Http\Controllers
 */
class AttendanceLedgerController extends Controller
{
    /**
     * Display a listing of attendance ledgers with search, filter, sort, and pagination.
     *
     * Uses the HasTableQuery trait to handle dynamic querying.
     * Renders the Misc/AttendanceLedgers Vue component or returns JSON for API requests.
     *
     * @param Request $request The HTTP request containing query parameters.
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     * @throws \Exception If query fails or no active school is found.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', AttendanceLedger::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Define extra fields for table query
            $extraFields = [
                'attendance_session' => ['field' => 'attendance_session.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'attendable' => ['field' => 'attendable.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
            ];

            // Build query
            $query = AttendanceLedger::with([
                'attendanceSession:id,name',
                'attendable' => function ($query) {
                    $query->select('id', 'first_name', 'last_name');
                },
            ])->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $attendanceLedgers = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($attendanceLedgers);
            }

            return Inertia::render('Misc/AttendanceLedgers', [
                'attendanceLedgers' => $attendanceLedgers,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'attendanceSessions' => AttendanceSession::where('school_id', $school->id)->select('id', 'name')->get(),
                'attendables' => [
                    'students' => Student::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
                    'staff' => Staff::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch attendance ledgers: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch attendance ledgers'], 500)
                : redirect()->back()->with('error', 'Failed to load attendance ledgers.');
        }
    }

    /**
     * Show the form for creating a new attendance ledger.
     *
     * Renders the Misc/AttendanceLedgerCreate Vue component.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        Gate::authorize('create', AttendanceLedger::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            return Inertia::render('Misc/AttendanceLedgerCreate', [
                'attendanceSessions' => AttendanceSession::where('school_id', $school->id)->select('id', 'name')->get(),
                'attendables' => [
                    'students' => Student::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
                    'staff' => Staff::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load attendance ledger creation form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load attendance ledger creation form.');
        }
    }

    /**
     * Store a newly created attendance ledger in storage.
     *
     * Validates the input, creates the attendance ledger, and sends notifications.
     *
     * @param Request $request The HTTP request containing attendance ledger data.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If creation fails.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', AttendanceLedger::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'attendance_session_id' => 'required|exists:attendance_sessions,id,school_id,' . $school->id,
                'attendable_id' => 'required|uuid',
                'attendable_type' => 'required|in:App\Models\Student\Student,App\Models\Employee\Staff',
                'status' => 'required|in:present,absent,late,leave,holiday',
                'remarks' => 'nullable|string',
            ])->validate();

            // Verify attendable exists within the school
            $attendableType = $validated['attendable_type'];
            $attendable = $attendableType::where('id', $validated['attendable_id'])
                ->where('school_id', $school->id)
                ->first();
            if (!$attendable) {
                throw ValidationException::withMessages([
                    'attendable_id' => 'The selected attendable is invalid or does not belong to this school.',
                ]);
            }

            // Create the attendance ledger
            $attendanceLedger = AttendanceLedger::create([
                'school_id' => $school->id,
                'attendance_session_id' => $validated['attendance_session_id'],
                'attendable_id' => $validated['attendable_id'],
                'attendable_type' => $validated['attendable_type'],
                'status' => $validated['status'],
                'remarks' => $validated['remarks'],
            ]);

            // Get the attendance session
             $session = AttendanceSession::findOrFail($validated['attendance_session_id']);

             // Mark attendance using the trait
             $method = 'mark' . ucfirst($validated['status']);
             if (!method_exists($attendable, $method)) {
                 throw new \Exception('Invalid attendance status.');
             }
             $attendanceLedger = $attendable->$method($session, $validated['remarks']);

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new AttendanceLedgerAction($attendanceLedger, 'created'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Attendance ledger created successfully'], 201)
                : redirect()->route('attendance-ledgers.index')->with('success', 'Attendance ledger created successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to create attendance ledger: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create attendance ledger'], 500)
                : redirect()->back()->with('error', 'Failed to create attendance ledger.');
        }
    }

    /**
     * Display the specified attendance ledger.
     *
     * Loads the attendance ledger with related data and returns a JSON response.
     *
     * @param Request $request The HTTP request.
     * @param AttendanceLedger $attendanceLedger The attendance ledger to display.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If no active school is found or attendance ledger is not accessible.
     */
    public function show(Request $request, AttendanceLedger $attendanceLedger)
    {
        Gate::authorize('view', $attendanceLedger);

        try {
            $school = GetSchoolModel();
            if (!$school || $attendanceLedger->school_id !== $school->id) {
                throw new \Exception('Attendance ledger not found or not accessible.');
            }

            $attendanceLedger->load([
                'attendanceSession',
                'attendable' => function ($query) {
                    $query->select('id', 'first_name', 'last_name');
                },
            ]);

            return response()->json(['attendanceLedger' => $attendanceLedger]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch attendance ledger: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch attendance ledger'], 500);
        }
    }

    /**
     * Show the form for editing the specified attendance ledger.
     *
     * Renders the Misc/AttendanceLedgerEdit Vue component.
     *
     * @param AttendanceLedger $attendanceLedger The attendance ledger to edit.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found or attendance ledger is not accessible.
     */
    public function edit(AttendanceLedger $attendanceLedger)
    {
        Gate::authorize('update', $attendanceLedger);

        try {
            $school = GetSchoolModel();
            if (!$school || $attendanceLedger->school_id !== $school->id) {
                throw new \Exception('Attendance ledger not found or not accessible.');
            }

            $attendanceLedger->load(['attendanceSession', 'attendable']);

            return Inertia::render('Misc/AttendanceLedgerEdit', [
                'attendanceLedger' => $attendanceLedger,
                'attendanceSessions' => AttendanceSession::where('school_id', $school->id)->select('id', 'name')->get(),
                'attendables' => [
                    'students' => Student::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
                    'staff' => Staff::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load attendance ledger edit form: ' . $e->getMessage());
            return redirect()->route('attendance-ledgers.index')->with('error', 'Failed to load attendance ledger edit form.');
        }
    }

    /**
     * Update the specified attendance ledger in storage.
     *
     * Validates the input, updates the attendance ledger, and sends notifications.
     *
     * @param Request $request The HTTP request containing updated attendance ledger data.
     * @param AttendanceLedger $attendanceLedger The attendance ledger to update.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If update fails.
     */
    public function update(Request $request, AttendanceLedger $attendanceLedger)
    {
        Gate::authorize('update', $attendanceLedger);

        try {
            $school = GetSchoolModel();
            if (!$school || $attendanceLedger->school_id !== $school->id) {
                throw new \Exception('Attendance ledger not found or not accessible.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'attendance_session_id' => 'required|exists:attendance_sessions,id,school_id,' . $school->id,
                'attendable_id' => 'required|uuid',
                'attendable_type' => 'required|in:App\Models\Student\Student,App\Models\Employee\Staff',
                'status' => 'required|in:present,absent,late,leave,holiday',
                'remarks' => 'nullable|string',
            ])->validate();

            // Verify attendable exists within the school
            $attendableType = $validated['attendable_type'];
            $attendable = $attendableType::where('id', $validated['attendable_id'])
                ->where('school_id', $school->id)
                ->first();
            if (!$attendable) {
                throw ValidationException::withMessages([
                    'attendable_id' => 'The selected attendable is invalid or does not belong to this school.',
                ]);
            }

            // Update the attendance ledger
            $attendanceLedger->update([
                'attendance_session_id' => $validated['attendance_session_id'],
                'attendable_id' => $validated['attendable_id'],
                'attendable_type' => $validated['attendable_type'],
                'status' => $validated['status'],
                'remarks' => $validated['remarks'],
            ]);

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new AttendanceLedgerAction($attendanceLedger, 'updated'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Attendance ledger updated successfully'])
                : redirect()->route('attendance-ledgers.index')->with('success', 'Attendance ledger updated successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update attendance ledger: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update attendance ledger'], 500)
                : redirect()->back()->with('error', 'Failed to update attendance ledger.');
        }
    }

    /**
     * Remove one or more attendance ledgers from storage (soft or force delete).
     *
     * Accepts an array of attendance ledger IDs via JSON request, performs soft or force delete,
     * and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of attendance ledger IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If deletion fails or attendance ledgers are not accessible.
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', AttendanceLedger::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:attendance_ledgers,id,school_id,' . $school->id,
                'force' => 'sometimes|boolean',
            ])->validate();

            // Notify before deletion
            $attendanceLedgers = AttendanceLedger::whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            foreach ($attendanceLedgers as $attendanceLedger) {
                Notification::send($users, new AttendanceLedgerAction($attendanceLedger, 'deleted'));
            }

            // Perform soft or force delete
            $forceDelete = $request->boolean('force');
            $query = AttendanceLedger::whereIn('id', $validated['ids'])->where('school_id', $school->id);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? "$deleted attendance ledger(s) deleted successfully" : "No attendance ledgers were deleted";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('attendance-ledgers.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to delete attendance ledgers: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete attendance ledger(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete attendance ledger(s).');
        }
    }

    /**
     * Restore one or more soft-deleted attendance ledgers.
     *
     * Accepts an array of attendance ledger IDs via JSON request, restores them, and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of attendance ledger IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If restoration fails or attendance ledgers are not accessible.
     */
    public function restore(Request $request)
    {
        Gate::authorize('restore', AttendanceLedger::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:attendance_ledgers,id,school_id,' . $school->id,
            ])->validate();

            // Notify before restoration
            $attendanceLedgers = AttendanceLedger::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            foreach ($attendanceLedgers as $attendanceLedger) {
                Notification::send($users, new AttendanceLedgerAction($attendanceLedger, 'restored'));
            }

            // Restore the attendance ledgers
            $count = AttendanceLedger::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->restore();

            $message = $count ? "$count attendance ledger(s) restored successfully" : "No attendance ledgers were restored";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('attendance-ledgers.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to restore attendance ledgers: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to restore attendance ledger(s)'], 500)
                : redirect()->back()->with('error', 'Failed to restore attendance ledger(s).');
        }
    }


    /**
     * Mark attendance for multiple entities in a single request.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If bulk marking fails.
     */
    public function bulkMark(Request $request)
    {
        Gate::authorize('create', AttendanceLedger::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'attendance_session_id' => 'required|exists:attendance_sessions,id,school_id,' . $school->id,
                'attendable_type' => 'required|in:App\Models\Student\Student,App\Models\Employee\Staff',
                'attendable_ids' => 'required|array|min:1',
                'attendable_ids.*' => 'uuid',
                'status' => 'required|in:present,absent,late,leave,holiday',
                'remarks' => 'nullable|string',
            ])->validate();

            // Find the attendance session
            $session = AttendanceSession::findOrFail($validated['attendance_session_id']);

            // Find the attendables
            $attendableType = $validated['attendable_type'];
            $entities = $attendableType::whereIn('id', $validated['attendable_ids'])
                ->where('school_id', $school->id)
                ->get();

            if ($entities->count() !== count($validated['attendable_ids'])) {
                throw ValidationException::withMessages([
                    'attendable_ids' => 'One or more selected entities are invalid or do not belong to this school.',
                ]);
            }

            // Mark bulk attendance using the trait
            $attendanceLedgers = $attendableType::markBulkAttendance(
                $entities,
                $session,
                $validated['status'],
                $validated['remarks']
            );

            return $request->wantsJson()
                ? response()->json(['message' => "Marked attendance for {$attendanceLedgers->count()} entities"], 201)
                : redirect()->route('attendance-ledgers.index')->with('success', "Marked attendance for {$attendanceLedgers->count()} entities.");
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to mark bulk attendance: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to mark bulk attendance'], 500)
                : redirect()->back()->with('error', 'Failed to mark bulk attendance.');
        }
    }
}
