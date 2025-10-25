<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignDriverRequest;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Employee\Staff;
use App\Models\Transport\Vehicle\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing Vehicle resources in a multi-tenant school management system.
 */
class VehicleController extends Controller
{
    /**
     * Display a listing of vehicles with dynamic querying.
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Vehicle::class);

        try {
            $school = GetSchoolModel();

            // Define extra fields for table query
            $extraFields = [
                [
                    'field' => 'current_driver_name',
                    'relation' => 'currentDriver.staff.user',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            // Build query
            $query = Vehicle::with(['school:id,name', 'currentDriver.staff.user:id,name', 'routes:id,name'])
                ->withCount(['routes', 'users'])
                ->where('school_id', $school?->id)
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $vehicles = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($vehicles);
            }

            return Inertia::render('Transport/Vehicles/Index', [ // UI path: resources/js/Pages/Transport/Vehicles/Index.vue
                'vehicles' => $vehicles,
                'staff' => Staff::where('school_id', $school?->id)->with('user:id,name')->get(['id', 'user_id']),
                'fuelTypes' => [], // Populate if VehicleFuelType model exists
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch vehicles: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch vehicles'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch vehicles']);
        }
    }

    /**
     * Store a newly created vehicle in storage.
     *
     * @param StoreVehicleRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreVehicleRequest $request)
    {
        Gate::authorize('create', Vehicle::class);

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id;

            $vehicle = Vehicle::create($validated);
            if ($request->has('staff_id') && $request->input('staff_id')) {
                $vehicle->driverAssignments()->create([
                    'staff_id' => $request->input('staff_id'),
                    'effective_date' => $request->input('effective_date', now()),
                    'options' => $request->input('driver_options', []),
                ]);
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Vehicle created successfully'], 201)
                : redirect()->route('vehicles.index')->with(['success' => 'Vehicle created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create vehicle: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create vehicle'], 500)
                : redirect()->back()->with(['error' => 'Failed to create vehicle'])->withInput();
        }
    }

    /**
     * Display the specified vehicle.
     *
     * @param Request $request
     * @param Vehicle $vehicle
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Vehicle $vehicle)
    {
        Gate::authorize('view', $vehicle);

        try {
            $vehicle->load(['school:id,name', 'currentDriver.staff.user:id,name', 'routes:id,name', 'users:id,name']);
            return response()->json(['vehicle' => $vehicle]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch vehicle: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch vehicle'], 500);
        }
    }

    /**
     * Update the specified vehicle in storage.
     *
     * @param UpdateVehicleRequest $request
     * @param Vehicle $vehicle
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle)
    {
        Gate::authorize('update', $vehicle);

        try {
            $validated = $request->validated();
            $vehicle->update($validated);
            if ($request->has('staff_id') && $request->input('staff_id')) {
                $currentAssignment = $vehicle->currentDriver()->first();
                if ($currentAssignment && $currentAssignment->staff_id !== $request->input('staff_id')) {
                    $currentAssignment->update(['unassigned_at' => now()]);
                    $vehicle->driverAssignments()->create([
                        'staff_id' => $request->input('staff_id'),
                        'effective_date' => $request->input('effective_date', now()),
                        'options' => $request->input('driver_options', []),
                    ]);
                } elseif (!$currentAssignment) {
                    $vehicle->driverAssignments()->create([
                        'staff_id' => $request->input('staff_id'),
                        'effective_date' => $request->input('effective_date', now()),
                        'options' => $request->input('driver_options', []),
                    ]);
                }
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Vehicle updated successfully'])
                : redirect()->route('vehicles.index')->with(['success' => 'Vehicle updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update vehicle: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update vehicle'], 500)
                : redirect()->back()->with(['error' => 'Failed to update vehicle'])->withInput();
        }
    }

    /**
     * Assign a driver to the specified vehicle.
     *
     * @param AssignDriverRequest $request
     * @param Vehicle $vehicle
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function assignDriver(AssignDriverRequest $request, Vehicle $vehicle)
    {
        Gate::authorize('assignDriver', $vehicle);

        try {
            $validated = $request->validated();

            // End any existing active assignment
            $currentAssignment = $vehicle->currentDriver()->first();
            if ($currentAssignment && $currentAssignment->staff_id !== $validated['staff_id']) {
                $currentAssignment->update(['unassigned_at' => now()]);
            }

            // Create new assignment if no active assignment exists or staff_id differs
            if (!$currentAssignment || $currentAssignment->staff_id !== $validated['staff_id']) {
                $vehicle->driverAssignments()->create([
                    'staff_id' => $validated['staff_id'],
                    'effective_date' => $validated['effective_date'] ?? now(),
                    'options' => $validated['options'] ?? [],
                ]);
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Driver assigned successfully'])
                : redirect()->route('vehicles.index')->with(['success' => 'Driver assigned successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to assign driver: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to assign driver'], 500)
                : redirect()->back()->with(['error' => 'Failed to assign driver'])->withInput();
        }
    }

    /**
     * Remove the specified vehicle(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', Vehicle::class);

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:vehicles,id',
            ]);

            $school = GetSchoolModel();
            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');

            $query = Vehicle::whereIn('id', $ids)->where('school_id', $school?->id);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? 'Vehicle(s) deleted successfully' : 'No vehicles were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('vehicles.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete vehicles: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete vehicles'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete vehicles']);
        }
    }

    /**
     * Restore a soft-deleted vehicle.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $vehicle = Vehicle::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $vehicle);

        try {
            $vehicle->restore();
            return response()->json(['message' => 'Vehicle restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore vehicle: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore vehicle'], 500);
        }
    }
}
