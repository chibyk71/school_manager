<?php

namespace App\Http\Controllers;

use App\Models\Academic\ClassPeriod;
use App\Models\Academic\ClassSection;
use App\Models\Employee\Staff;
use App\Models\Misc\AttendanceSession;
use App\Notifications\AttendanceSessionAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Controller for managing attendance sessions in the school management system.
 *
 * Handles CRUD operations, including soft delete, force delete, and restore,
 * for attendance sessions, ensuring proper authorization, validation, school scoping,
 * and notifications for a multi-tenant SaaS environment.
 *
 * @package App\Http\Controllers
 */
class AttendanceSessionController extends Controller
{
    /**
     * Display a listing of attendance sessions with search, filter, sort, and pagination.
     *
     * Uses the HasTableQuery trait to handle dynamic querying.
     * Renders the Misc/AttendanceSessions Vue component or returns JSON for API requests.
     *
     * @param Request $request The HTTP request containing query parameters.
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     * @throws \Exception If query fails or no active school is found.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', AttendanceSession::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Define extra fields for table query
            $extraFields = [
                'class_section' => ['field' => 'class_section.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'class_period' => ['field' => 'class_period.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'manager' => ['field' => 'manager.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
            ];

            // Build query
            $query = AttendanceSession::with([
                'classSection:id,name',
                'classPeriod:id,name',
                'manager:id,name',
            ])->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $attendanceSessions = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($attendanceSessions);
            }

            return Inertia::render('Misc/AttendanceSessions', [
                'attendanceSessions' => $attendanceSessions,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'classSections' => ClassSection::where('school_id', $school->id)->select('id', 'name')->get(),
                'classPeriods' => ClassPeriod::where('school_id', $school->id)->select('id', 'name')->get(),
                'managers' => Staff::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                    ->select('id', 'first_name', 'last_name')
                    ->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch attendance sessions: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch attendance sessions'], 500)
                : redirect()->back()->with('error', 'Failed to load attendance sessions.');
        }
    }

    /**
     * Show the form for creating a new attendance session.
     *
     * Renders the Misc/AttendanceSessionCreate Vue component.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        Gate::authorize('create', AttendanceSession::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            return Inertia::render('Misc/AttendanceSessionCreate', [
                'classSections' => ClassSection::where('school_id', $school->id)->select('id', 'name')->get(),
                'classPeriods' => ClassPeriod::where('school_id', $school->id)->select('id', 'name')->get(),
                'managers' => Staff::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                    ->select('id', 'first_name', 'last_name')
                    ->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load attendance session creation form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load attendance session creation form.');
        }
    }

    /**
     * Store a newly created attendance session in storage.
     *
     * Validates the input, creates the attendance session, and sends notifications.
     *
     * @param Request $request The HTTP request containing attendance session data.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If creation fails.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', AttendanceSession::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'class_section_id' => 'required|exists:class_sections,id,school_id,' . $school->id,
                'class_period_id' => 'required|exists:class_periods,id,school_id,' . $school->id,
                'manager_id' => 'required|exists:users,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'date_effective' => 'required|date',
                'configs' => 'nullable|array',
            ])->validate();

            // Verify manager belongs to the school
            $manager = Staff::where('id', $validated['manager_id'])
                ->where('school_id', $school->id)
                ->first();
            if (!$manager) {
                throw ValidationException::withMessages([
                    'manager_id' => 'The selected manager is invalid or does not belong to this school.',
                ]);
            }

            // Create the attendance session
            $attendanceSession = AttendanceSession::create([
                'school_id' => $school->id,
                'class_section_id' => $validated['class_section_id'],
                'class_period_id' => $validated['class_period_id'],
                'manager_id' => $validated['manager_id'],
                'name' => $validated['name'],
                'description' => $validated['description'],
                'date_effective' => $validated['date_effective'],
                'configs' => $validated['configs'],
            ]);

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new AttendanceSessionAction($attendanceSession, 'created'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Attendance session created successfully'], 201)
                : redirect()->route('attendance-sessions.index')->with('success', 'Attendance session created successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to create attendance session: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create attendance session'], 500)
                : redirect()->back()->with('error', 'Failed to create attendance session.');
        }
    }

    /**
     * Display the specified attendance session.
     *
     * Loads the attendance session with related data and returns a JSON response.
     *
     * @param Request $request The HTTP request.
     * @param AttendanceSession $attendanceSession The attendance session to display.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If no active school is found or attendance session is not accessible.
     */
    public function show(Request $request, AttendanceSession $attendanceSession)
    {
        Gate::authorize('view', $attendanceSession);

        try {
            $school = GetSchoolModel();
            if (!$school || $attendanceSession->school_id !== $school->id) {
                throw new \Exception('Attendance session not found or not accessible.');
            }

            $attendanceSession->load([
                'classSection',
                'classPeriod',
                'manager',
                'attendanceLedgers' => function ($query) {
                    $query->with(['attendable' => function ($q) {
                        $q->select('id', 'first_name', 'last_name');
                    }]);
                },
            ]);

            return response()->json(['attendanceSession' => $attendanceSession]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch attendance session: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch attendance session'], 500);
        }
    }

    /**
     * Show the form for editing the specified attendance session.
     *
     * Renders the Misc/AttendanceSessionEdit Vue component.
     *
     * @param AttendanceSession $attendanceSession The attendance session to edit.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found or attendance session is not accessible.
     */
    public function edit(AttendanceSession $attendanceSession)
    {
        Gate::authorize('update', $attendanceSession);

        try {
            $school = GetSchoolModel();
            if (!$school || $attendanceSession->school_id !== $school->id) {
                throw new \Exception('Attendance session not found or not accessible.');
            }

            $attendanceSession->load(['classSection', 'classPeriod', 'manager']);

            return Inertia::render('Misc/AttendanceSessionEdit', [
                'attendanceSession' => $attendanceSession,
                'classSections' => ClassSection::where('school_id', $school->id)->select('id', 'name')->get(),
                'classPeriods' => ClassPeriod::where('school_id', $school->id)->select('id', 'name')->get(),
                'managers' => Staff::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                    ->select('id', 'first_name', 'last_name')
                    ->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load attendance session edit form: ' . $e->getMessage());
            return redirect()->route('attendance-sessions.index')->with('error', 'Failed to load attendance session edit form.');
        }
    }

    /**
     * Update the specified attendance session in storage.
     *
     * Validates the input, updates the attendance session, and sends notifications.
     *
     * @param Request $request The HTTP request containing updated attendance session data.
     * @param AttendanceSession $attendanceSession The attendance session to update.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If update fails.
     */
    public function update(Request $request, AttendanceSession $attendanceSession)
    {
        Gate::authorize('update', $attendanceSession);

        try {
            $school = GetSchoolModel();
            if (!$school || $attendanceSession->school_id !== $school->id) {
                throw new \Exception('Attendance session not found or not accessible.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'class_section_id' => 'required|exists:class_sections,id,school_id,' . $school->id,
                'class_period_id' => 'required|exists:class_periods,id,school_id,' . $school->id,
                'manager_id' => 'required|exists:users,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'date_effective' => 'required|date',
                'configs' => 'nullable|array',
            ])->validate();

            // Verify manager belongs to the school
            $manager = Staff::where('id', $validated['manager_id'])
                ->where('school_id', $school->id)
                ->first();
            if (!$manager) {
                throw ValidationException::withMessages([
                    'manager_id' => 'The selected manager is invalid or does not belong to this school.',
                ]);
            }

            // Update the attendance session
            $attendanceSession->update([
                'class_section_id' => $validated['class_section_id'],
                'class_period_id' => $validated['class_period_id'],
                'manager_id' => $validated['manager_id'],
                'name' => $validated['name'],
                'description' => $validated['description'],
                'date_effective' => $validated['date_effective'],
                'configs' => $validated['configs'],
            ]);

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new AttendanceSessionAction($attendanceSession, 'updated'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Attendance session updated successfully'])
                : redirect()->route('attendance-sessions.index')->with('success', 'Attendance session updated successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update attendance session: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update attendance session'], 500)
                : redirect()->back()->with('error', 'Failed to update attendance session.');
        }
    }

    /**
     * Remove one or more attendance sessions from storage (soft or force delete).
     *
     * Accepts an array of attendance session IDs via JSON request, performs soft or force delete,
     * and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of attendance session IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If deletion fails or attendance sessions are not accessible.
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', AttendanceSession::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:attendance_sessions,id,school_id,' . $school->id,
                'force' => 'sometimes|boolean',
            ])->validate();

            // Notify before deletion
            $attendanceSessions = AttendanceSession::whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            foreach ($attendanceSessions as $attendanceSession) {
                Notification::send($users, new AttendanceSessionAction($attendanceSession, 'deleted'));
            }

            // Perform soft or force delete
            $forceDelete = $request->boolean('force');
            $query = AttendanceSession::whereIn('id', $validated['ids'])->where('school_id', $school->id);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? "$deleted attendance session(s) deleted successfully" : "No attendance sessions were deleted";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('attendance-sessions.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to delete attendance sessions: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete attendance session(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete attendance session(s).');
        }
    }

    /**
     * Restore one or more soft-deleted attendance sessions.
     *
     * Accepts an array of attendance session IDs via JSON request, restores them, and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of attendance session IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If restoration fails or attendance sessions are not accessible.
     */
    public function restore(Request $request)
    {
        Gate::authorize('restore', AttendanceSession::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:attendance_sessions,id,school_id,' . $school->id,
            ])->validate();

            // Notify before restoration
            $attendanceSessions = AttendanceSession::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            foreach ($attendanceSessions as $attendanceSession) {
                Notification::send($users, new AttendanceSessionAction($attendanceSession, 'restored'));
            }

            // Restore the attendance sessions
            $count = AttendanceSession::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->restore();

            $message = $count ? "$count attendance session(s) restored successfully" : "No attendance sessions were restored";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('attendance-sessions.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to restore attendance sessions: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to restore attendance session(s)'], 500)
                : redirect()->back()->with('error', 'Failed to restore attendance session(s).');
        }
    }

}
