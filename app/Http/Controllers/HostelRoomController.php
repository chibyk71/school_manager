<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHostelRoomRequest;
use App\Http\Requests\UpdateHostelRoomRequest;
use App\Models\Housing\Hostel;
use App\Models\Housing\HostelRoom;
use App\Models\Employee\Staff;
use App\Notifications\HostelRoomAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Controller for managing hostel rooms in the school management system.
 *
 * Handles CRUD operations, including soft delete, force delete, and restore,
 * for hostel rooms, ensuring proper authorization, validation, school scoping,
 * and notifications for a multi-tenant SaaS environment.
 *
 * @package App\Http\Controllers
 */
class HostelRoomController extends Controller
{
    /**
     * Display a listing of hostel rooms with search, filter, sort, and pagination.
     *
     * Uses the HasTableQuery trait to handle dynamic querying.
     * Renders the Housing/HostelRooms Vue component or returns JSON for API requests.
     *
     * @param Request $request The HTTP request containing query parameters.
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     * @throws \Exception If query fails or no active school is found.
     */
    public function index(Request $request)
    {
        permitted('hostel-room-view-any');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Define extra fields for table query
            $extraFields = [
                'hostel' => [
                    'field' => 'hostel.name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                    'relation' => 'hostel',
                    'relatedField' => 'name',
                ],
            ];

            // Build query
            $query = HostelRoom::with([
                'hostel:id,name',
            ])->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $hostelRooms = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($hostelRooms);
            }

            return Inertia::render('Housing/HostelRooms', [
                'hostelRooms' => $hostelRooms,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'hostels' => Hostel::where('school_id', $school->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch hostel rooms: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch hostel rooms'], 500)
                : redirect()->back()->with('error', 'Failed to load hostel rooms.');
        }
    }

    /**
     * Show the form for creating a new hostel room.
     *
     * Renders the Housing/HostelRoomCreate Vue component.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        permitted('hostel-room-create');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            return Inertia::render('Housing/HostelRoomCreate', [
                'hostels' => Hostel::where('school_id', $school->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load hostel room creation form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load hostel room creation form.');
        }
    }

    /**
     * Store a newly created hostel room in storage.
     *
     * Validates the input, creates the hostel room, and sends notifications.
     *
     * @param StoreHostelRoomRequest $request The validated HTTP request containing hostel room data.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Exception If creation fails.
     */
    public function store(StoreHostelRoomRequest $request)
    {
        permitted('hostel-room-create');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Create the hostel room
            $hostelRoom = HostelRoom::create($request->validated());

            // Notify admins and wardens
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'warden']))
                ->get();
            Notification::send($users, new HostelRoomAction($hostelRoom, 'created'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Hostel room created successfully'], 201)
                : redirect()->route('hostel-rooms.index')->with('success', 'Hostel room created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create hostel room: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create hostel room'], 500)
                : redirect()->back()->with('error', 'Failed to create hostel room.');
        }
    }

    /**
     * Display the specified hostel room.
     *
     * Loads the hostel room with related data and returns a JSON response.
     *
     * @param Request $request The HTTP request.
     * @param HostelRoom $hostelRoom The hostel room instance to display.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If no active school is found or hostel room is not accessible.
     */
    public function show(Request $request, HostelRoom $hostelRoom)
    {
        permitted('hostel-room-view');

        try {
            $school = GetSchoolModel();
            if (!$school || $hostelRoom->hostel->school_id !== $school->id) {
                throw new \Exception('Hostel room not found or not accessible.');
            }

            $hostelRoom->load(['hostel', 'assignments']);

            return response()->json(['hostel_room' => $hostelRoom]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch hostel room: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch hostel room'], 500);
        }
    }

    /**
     * Show the form for editing the specified hostel room.
     *
     * Renders the Housing/HostelRoomEdit Vue component.
     *
     * @param HostelRoom $hostelRoom The hostel room instance to edit.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found or hostel room is not accessible.
     */
    public function edit(HostelRoom $hostelRoom)
    {
        permitted('hostel-room-update');

        try {
            $school = GetSchoolModel();
            if (!$school || $hostelRoom->hostel->school_id !== $school->id) {
                throw new \Exception('Hostel room not found or not accessible.');
            }

            $hostelRoom->load(['hostel']);

            return Inertia::render('Housing/HostelRoomEdit', [
                'hostelRoom' => $hostelRoom,
                'hostels' => Hostel::where('school_id', $school->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load hostel room edit form: ' . $e->getMessage());
            return redirect()->route('hostel-rooms.index')->with('error', 'Failed to load hostel room edit form.');
        }
    }

    /**
     * Update the specified hostel room in storage.
     *
     * Validates the input, updates the hostel room, and sends notifications.
     *
     * @param UpdateHostelRoomRequest $request The validated HTTP request containing updated hostel room data.
     * @param HostelRoom $hostelRoom The hostel room instance to update.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Exception If update fails.
     */
    public function update(UpdateHostelRoomRequest $request, HostelRoom $hostelRoom)
    {
        permitted('hostel-room-update');

        try {
            $school = GetSchoolModel();
            if (!$school || $hostelRoom->hostel->school_id !== $school->id) {
                throw new \Exception('Hostel room not found or not accessible.');
            }

            // Update the hostel room
            $hostelRoom->update($request->validated());

            // Notify admins and wardens
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'warden']))
                ->get();
            Notification::send($users, new HostelRoomAction($hostelRoom, 'updated'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Hostel room updated successfully'])
                : redirect()->route('hostel-rooms.index')->with('success', 'Hostel room updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update hostel room: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update hostel room'], 500)
                : redirect()->back()->with('error', 'Failed to update hostel room.');
        }
    }

    /**
     * Remove one or more hostel rooms from storage (soft or force delete).
     *
     * Accepts an array of hostel room IDs via JSON request, performs soft or force delete,
     * and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of hostel room IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If deletion fails or hostel rooms are not accessible.
     */
    public function destroy(Request $request)
    {
        permitted('hostel-room-delete');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:hostel_rooms,id',
                'force' => 'sometimes|boolean',
            ])->validate();

            // Ensure all hostel rooms belong to the current school
            $hostelRooms = HostelRoom::whereIn('id', $validated['ids'])
                ->whereHas('hostel', fn($q) => $q->where('school_id', $school->id))
                ->get();

            if ($hostelRooms->count() !== count($validated['ids'])) {
                throw new \Exception('Some hostel rooms are not accessible.');
            }

            // Notify before deletion
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'warden']))
                ->get();
            foreach ($hostelRooms as $hostelRoom) {
                Notification::send($users, new HostelRoomAction($hostelRoom, 'deleted'));
            }

            // Perform soft or force delete
            $forceDelete = $request->boolean('force');
            $query = HostelRoom::whereIn('id', $validated['ids'])
                ->whereHas('hostel', fn($q) => $q->where('school_id', $school->id));
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? "$deleted hostel rooms deleted successfully" : "No hostel rooms were deleted";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('hostel-rooms.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to delete hostel rooms: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete hostel rooms'], 500)
                : redirect()->back()->with('error', 'Failed to delete hostel rooms.');
        }
    }

    /**
     * Restore one or more soft-deleted hostel rooms.
     *
     * Accepts an array of hostel room IDs via JSON request, restores them, and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of hostel room IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If restoration fails or hostel rooms are not accessible.
     */
    public function restore(Request $request)
    {
        permitted('hostel-room-restore');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:hostel_rooms,id',
            ])->validate();

            // Ensure all hostel rooms belong to the current school
            $hostelRooms = HostelRoom::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->whereHas('hostel', fn($q) => $q->where('school_id', $school->id))
                ->get();

            if ($hostelRooms->count() !== count($validated['ids'])) {
                throw new \Exception('Some hostel rooms are not accessible.');
            }

            // Notify before restoration
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'warden']))
                ->get();
            foreach ($hostelRooms as $hostelRoom) {
                Notification::send($users, new HostelRoomAction($hostelRoom, 'restored'));
            }

            // Restore the hostel rooms
            $count = HostelRoom::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->whereHas('hostel', fn($q) => $q->where('school_id', $school->id))
                ->restore();

            $message = $count ? "$count hostel rooms restored successfully" : "No hostel rooms were restored";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('hostel-rooms.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to restore hostel rooms: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to restore hostel rooms'], 500)
                : redirect()->back()->with('error', 'Failed to restore hostel rooms.');
        }
    }
}
