<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHostelAssignmentRequest;
use App\Http\Requests\UpdateHostelAssignmentRequest;
use App\Models\Housing\Hostel;
use App\Models\Housing\HostelRoom;
use App\Models\Housing\HostelAssignment;
use App\Models\Employee\Staff;
use App\Models\Academic\Student;
use App\Notifications\HostelAssignmentAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Controller for managing hostel assignments in the school management system.
 *
 * Handles CRUD operations, including soft delete, force delete, and restore,
 * for hostel assignments, ensuring proper authorization, validation, school scoping,
 * and notifications for a multi-tenant SaaS environment.
 *
 * @package App\Http\Controllers
 */
class HostelAssignmentController extends Controller
{
    /**
     * Display a listing of hostel assignments with search, filter, sort, and pagination.
     *
     * Uses the HasTableQuery trait to handle dynamic querying.
     * Renders the Housing/HostelAssignments Vue component or returns JSON for API requests.
     *
     * @param Request $request The HTTP request containing query parameters.
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     * @throws \Exception If query fails or no active school is found.
     */
    public function index(Request $request)
    {
        permitted('hostel-assignment-view-any');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Define extra fields for table query
            $extraFields = [
                'student' => [
                    'field' => 'student.first_name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                    'relation' => 'student',
                    'relatedField' => 'first_name',
                ],
                'hostel' => [
                    'field' => 'hostel_room.hostel.name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                    'relation' => 'room.hostel',
                    'relatedField' => 'name',
                ],
                'room' => [
                    'field' => 'hostel_room.room_number',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                    'relation' => 'room',
                    'relatedField' => 'room_number',
                ],
            ];

            // Build query
            $query = HostelAssignment::with([
                'student:id,first_name,last_name',
                'room:id,room_number,hostel_id',
                'room.hostel:id,name',
            ])->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $assignments = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($assignments);
            }

            return Inertia::render('Housing/HostelAssignments', [
                'hostelAssignments' => $assignments,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'hostels' => Hostel::where('school_id', $school->id)->select('id', 'name')->get(),
                'rooms' => HostelRoom::whereHas('hostel', fn($q) => $q->where('school_id', $school->id))
                    ->select('id', 'hostel_id', 'room_number', 'capacity')->get(),
                'students' => Student::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch hostel assignments: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch hostel assignments'], 500)
                : redirect()->back()->with('error', 'Failed to load hostel assignments.');
        }
    }

    /**
     * Show the form for creating a new hostel assignment.
     *
     * Renders the Housing/HostelAssignmentCreate Vue component.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        permitted('hostel-assignment-create');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            return Inertia::render('Housing/HostelAssignmentCreate', [
                'hostels' => Hostel::where('school_id', $school->id)->select('id', 'name')->get(),
                'rooms' => HostelRoom::whereHas('hostel', fn($q) => $q->where('school_id', $school->id))
                    ->select('id', 'hostel_id', 'room_number', 'capacity')->get(),
                'students' => Student::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load hostel assignment creation form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load hostel assignment creation form.');
        }
    }

    /**
     * Store a newly created hostel assignment in storage.
     *
     * Validates the input, creates the assignment, and sends notifications.
     *
     * @param StoreHostelAssignmentRequest $request The validated HTTP request containing assignment data.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Exception If creation fails.
     */
    public function store(StoreHostelAssignmentRequest $request)
    {
        permitted('hostel-assignment-create');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Create the hostel assignment
            $assignment = HostelAssignment::create($request->validated());

            // Notify admins, wardens, and the assigned student
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'warden']))
                ->get();
            $student = Student::find($request->student_id);
            Notification::send($users->merge([$student]), new HostelAssignmentAction($assignment, 'created'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Hostel assignment created successfully'], 201)
                : redirect()->route('hostel-assignments.index')->with('success', 'Hostel assignment created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create hostel assignment: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create hostel assignment'], 500)
                : redirect()->back()->with('error', 'Failed to create hostel assignment.');
        }
    }

    /**
     * Display the specified hostel assignment.
     *
     * Loads the assignment with related data and returns a JSON response.
     *
     * @param Request $request The HTTP request.
     * @param HostelAssignment $hostelAssignment The hostel assignment instance to display.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If no active school is found or assignment is not accessible.
     */
    public function show(Request $request, HostelAssignment $hostelAssignment)
    {
        permitted('hostel-assignment-view');

        try {
            $school = GetSchoolModel();
            if (!$school || $hostelAssignment->room->hostel->school_id !== $school->id) {
                throw new \Exception('Hostel assignment not found or not accessible.');
            }

            $hostelAssignment->load(['student', 'room', 'room.hostel']);

            return response()->json(['hostel_assignment' => $hostelAssignment]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch hostel assignment: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch hostel assignment'], 500);
        }
    }

    /**
     * Show the form for editing the specified hostel assignment.
     *
     * Renders the Housing/HostelAssignmentEdit Vue component.
     *
     * @param HostelAssignment $hostelAssignment The hostel assignment instance to edit.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found or assignment is not accessible.
     */
    public function edit(HostelAssignment $hostelAssignment)
    {
        permitted('hostel-assignment-update');

        try {
            $school = GetSchoolModel();
            if (!$school || $hostelAssignment->room->hostel->school_id !== $school->id) {
                throw new \Exception('Hostel assignment not found or not accessible.');
            }

            $hostelAssignment->load(['student', 'room', 'room.hostel']);

            return Inertia::render('Housing/HostelAssignmentEdit', [
                'hostelAssignment' => $hostelAssignment,
                'hostels' => Hostel::where('school_id', $school->id)->select('id', 'name')->get(),
                'rooms' => HostelRoom::whereHas('hostel', fn($q) => $q->where('school_id', $school->id))
                    ->select('id', 'hostel_id', 'room_number', 'capacity')->get(),
                'students' => Student::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load hostel assignment edit form: ' . $e->getMessage());
            return redirect()->route('hostel-assignments.index')->with('error', 'Failed to load hostel assignment edit form.');
        }
    }

    /**
     * Update the specified hostel assignment in storage.
     *
     * Validates the input, updates the assignment, and sends notifications.
     *
     * @param UpdateHostelAssignmentRequest $request The validated HTTP request containing updated assignment data.
     * @param HostelAssignment $hostelAssignment The hostel assignment instance to update.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Exception If update fails.
     */
    public function update(UpdateHostelAssignmentRequest $request, HostelAssignment $hostelAssignment)
    {
        permitted('hostel-assignment-update');

        try {
            $school = GetSchoolModel();
            if (!$school || $hostelAssignment->room->hostel->school_id !== $school->id) {
                throw new \Exception('Hostel assignment not found or not accessible.');
            }

            // Update the hostel assignment
            $hostelAssignment->update($request->validated());

            // Notify admins, wardens, and the assigned student
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'warden']))
                ->get();
            $student = Student::find($hostelAssignment->student_id);
            Notification::send($users->merge([$student]), new HostelAssignmentAction($hostelAssignment, 'updated'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Hostel assignment updated successfully'])
                : redirect()->route('hostel-assignments.index')->with('success', 'Hostel assignment updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update hostel assignment: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update hostel assignment'], 500)
                : redirect()->back()->with('error', 'Failed to update hostel assignment.');
        }
    }

    /**
     * Remove one or more hostel assignments from storage (soft or force delete).
     *
     * Accepts an array of assignment IDs via JSON request, performs soft or force delete,
     * and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of assignment IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If deletion fails or assignments are not accessible.
     */
    public function destroy(Request $request)
    {
        permitted('hostel-assignment-delete');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:hostel_assignments,id',
                'force' => 'sometimes|boolean',
            ])->validate();

            // Ensure all assignments belong to the current school
            $assignments = HostelAssignment::whereIn('id', $validated['ids'])
                ->whereHas('room.hostel', fn($q) => $q->where('school_id', $school->id))
                ->get();

            if ($assignments->count() !== count($validated['ids'])) {
                throw new \Exception('Some hostel assignments are not accessible.');
            }

            // Notify before deletion
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'warden']))
                ->get();
            foreach ($assignments as $assignment) {
                $student = Student::find($assignment->student_id);
                Notification::send($users->merge([$student]), new HostelAssignmentAction($assignment, 'deleted'));
            }

            // Perform soft or force delete
            $forceDelete = $request->boolean('force');
            $query = HostelAssignment::whereIn('id', $validated['ids'])
                ->whereHas('room.hostel', fn($q) => $q->where('school_id', $school->id));
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? "$deleted hostel assignments deleted successfully" : "No hostel assignments were deleted";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('hostel-assignments.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to delete hostel assignments: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete hostel assignments'], 500)
                : redirect()->back()->with('error', 'Failed to delete hostel assignments.');
        }
    }

    /**
     * Restore one or more soft-deleted hostel assignments.
     *
     * Accepts an array of assignment IDs via JSON request, restores them, and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of assignment IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If restoration fails or assignments are not accessible.
     */
    public function restore(Request $request)
    {
        permitted('hostel-assignment-restore');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:hostel_assignments,id',
            ])->validate();

            // Ensure all assignments belong to the current school
            $assignments = HostelAssignment::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->whereHas('room.hostel', fn($q) => $q->where('school_id', $school->id))
                ->get();

            if ($assignments->count() !== count($validated['ids'])) {
                throw new \Exception('Some hostel assignments are not accessible.');
            }

            // Check room capacity for each assignment
            foreach ($assignments as $assignment) {
                $room = HostelRoom::find($assignment->hostel_room_id);
                $currentOccupancy = HostelAssignment::where('hostel_room_id', $assignment->hostel_room_id)
                    ->where('status', 'checked-in')
                    ->where('id', '!=', $assignment->id)
                    ->count();
                if ($currentOccupancy >= $room->capacity) {
                    throw new \Exception("Room {$room->room_number} is at full capacity and cannot accept restored assignments.");
                }
            }

            // Notify before restoration
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'warden']))
                ->get();
            foreach ($assignments as $assignment) {
                $student = Student::find($assignment->student_id);
                Notification::send($users->merge([$student]), new HostelAssignmentAction($assignment, 'restored'));
            }

            // Restore the assignments
            $count = HostelAssignment::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->whereHas('room.hostel', fn($q) => $q->where('school_id', $school->id))
                ->restore();

            $message = $count ? "$count hostel assignments restored successfully" : "No hostel assignments were restored";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('hostel-assignments.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to restore hostel assignments: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to restore hostel assignments'], 500)
                : redirect()->back()->with('error', 'Failed to restore hostel assignments.');
        }
    }
}
