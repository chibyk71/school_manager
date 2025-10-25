<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHostelRequest;
use App\Http\Requests\UpdateHostelRequest;
use App\Models\Employee\Staff;
use App\Models\Housing\Hostel;
use App\Notifications\HostelAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Controller for managing hostels in the school management system.
 *
 * Handles CRUD operations, including soft delete, force delete, and restore,
 * for hostels, ensuring proper authorization, validation, school scoping,
 * and notifications for a multi-tenant SaaS environment.
 *
 * @package App\Http\Controllers
 */
class HostelController extends Controller
{
    /**
     * Display a listing of hostels with search, filter, sort, and pagination.
     *
     * Uses the HasTableQuery trait to handle dynamic querying.
     * Renders the Housing/Hostels Vue component or returns JSON for API requests.
     *
     * @param Request $request The HTTP request containing query parameters.
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     * @throws \Exception If query fails or no active school is found.
     */
    public function index(Request $request)
    {
        permitted('hostel-view-any');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Define extra fields for table query
            $extraFields = [
                'warden' => [
                    'field' => 'warden.first_name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                    'relation' => 'warden',
                    'relatedField' => 'first_name',
                ],
            ];

            // Build query
            $query = Hostel::with([
                'warden:id,first_name,last_name',
                'school:id,name',
            ])->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $hostels = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($hostels);
            }

            return Inertia::render('Housing/Hostels', [
                'hostels' => $hostels,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'staff' => Staff::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch hostels: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch hostels'], 500)
                : redirect()->back()->with('error', 'Failed to load hostels.');
        }
    }

    /**
     * Show the form for creating a new hostel.
     *
     * Renders the Housing/HostelCreate Vue component.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        permitted('hostel-create');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            return Inertia::render('Housing/HostelCreate', [
                'staff' => Staff::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load hostel creation form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load hostel creation form.');
        }
    }

    /**
     * Store a newly created hostel in storage.
     *
     * Validates the input, creates the hostel, and sends notifications.
     *
     * @param StoreHostelRequest $request The validated HTTP request containing hostel data.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Exception If creation fails.
     */
    public function store(StoreHostelRequest $request)
    {
        permitted('hostel-create');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Create the hostel
            $hostel = Hostel::create($request->validated());

            // Notify admins and wardens
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'warden']))
                ->get();
            Notification::send($users, new HostelAction($hostel, 'created'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Hostel created successfully'], 201)
                : redirect()->route('hostels.index')->with('success', 'Hostel created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create hostel: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create hostel'], 500)
                : redirect()->back()->with('error', 'Failed to create hostel.');
        }
    }

    /**
     * Display the specified hostel.
     *
     * Loads the hostel with related data and returns a JSON response.
     *
     * @param Request $request The HTTP request.
     * @param Hostel $hostel The hostel instance to display.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If no active school is found or hostel is not accessible.
     */
    public function show(Request $request, Hostel $hostel)
    {
        permitted('hostel-view');

        try {
            $school = GetSchoolModel();
            if (!$school || $hostel->school_id !== $school->id) {
                throw new \Exception('Hostel not found or not accessible.');
            }

            $hostel->load(['warden', 'school', 'rooms']);

            return response()->json(['hostel' => $hostel]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch hostel: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch hostel'], 500);
        }
    }

    /**
     * Show the form for editing the specified hostel.
     *
     * Renders the Housing/HostelEdit Vue component.
     *
     * @param Hostel $hostel The hostel instance to edit.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found or hostel is not accessible.
     */
    public function edit(Hostel $hostel)
    {
        permitted('hostel-update');

        try {
            $school = GetSchoolModel();
            if (!$school || $hostel->school_id !== $school->id) {
                throw new \Exception('Hostel not found or not accessible.');
            }

            $hostel->load(['warden', 'school']);

            return Inertia::render('Housing/HostelEdit', [
                'hostel' => $hostel,
                'staff' => Staff::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load hostel edit form: ' . $e->getMessage());
            return redirect()->route('hostels.index')->with('error', 'Failed to load hostel edit form.');
        }
    }

    /**
     * Update the specified hostel in storage.
     *
     * Validates the input, updates the hostel, and sends notifications.
     *
     * @param UpdateHostelRequest $request The validated HTTP request containing updated hostel data.
     * @param Hostel $hostel The hostel instance to update.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Exception If update fails.
     */
    public function update(UpdateHostelRequest $request, Hostel $hostel)
    {
        permitted('hostel-update');

        try {
            $school = GetSchoolModel();
            if (!$school || $hostel->school_id !== $school->id) {
                throw new \Exception('Hostel not found or not accessible.');
            }

            // Update the hostel
            $hostel->update($request->validated());

            // Notify admins and wardens
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'warden']))
                ->get();
            Notification::send($users, new HostelAction($hostel, 'updated'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Hostel updated successfully'])
                : redirect()->route('hostels.index')->with('success', 'Hostel updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update hostel: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update hostel'], 500)
                : redirect()->back()->with('error', 'Failed to update hostel.');
        }
    }

    /**
     * Remove one or more hostels from storage (soft or force delete).
     *
     * Accepts an array of hostel IDs via JSON request, performs soft or force delete,
     * and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of hostel IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If deletion fails or hostels are not accessible.
     */
    public function destroy(Request $request)
    {
        permitted('hostel-delete');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:hostels,id,school_id,' . $school->id,
                'force' => 'sometimes|boolean',
            ])->validate();

            // Notify before deletion
            $hostels = Hostel::whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'warden']))
                ->get();
            foreach ($hostels as $hostel) {
                Notification::send($users, new HostelAction($hostel, 'deleted'));
            }

            // Perform soft or force delete
            $forceDelete = $request->boolean('force');
            $query = Hostel::whereIn('id', $validated['ids'])->where('school_id', $school->id);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? "$deleted hostels deleted successfully" : "No hostels were deleted";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('hostels.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to delete hostels: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete hostels'], 500)
                : redirect()->back()->with('error', 'Failed to delete hostels.');
        }
    }

    /**
     * Restore one or more soft-deleted hostels.
     *
     * Accepts an array of hostel IDs via JSON request, restores them, and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of hostel IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If restoration fails or hostels are not accessible.
     */
    public function restore(Request $request)
    {
        permitted('hostel-restore');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:hostels,id,school_id,' . $school->id,
            ])->validate();

            // Notify before restoration
            $hostels = Hostel::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'warden']))
                ->get();
            foreach ($hostels as $hostel) {
                Notification::send($users, new HostelAction($hostel, 'restored'));
            }

            // Restore the hostels
            $count = Hostel::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->restore();

            $message = $count ? "$count hostels restored successfully" : "No hostels were restored";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('hostels.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to restore hostels: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to restore hostels'], 500)
                : redirect()->back()->with('error', 'Failed to restore hostels.');
        }
    }
}
