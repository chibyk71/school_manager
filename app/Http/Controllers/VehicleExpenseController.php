<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleExpenseRequest;
use App\Http\Requests\UpdateVehicleExpenseRequest;
use App\Models\Transport\Vehicle\Vehicle;
use App\Models\Transport\Vehicle\VehicleExpense;
use FarhanShares\MediaMan\MediaUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing VehicleExpense resources in a multi-tenant school management system.
 */
class VehicleExpenseController extends Controller
{
    /**
     * Display a listing of vehicle expenses with dynamic querying.
     *
     * @param Request $request
     * @param Vehicle $vehicle
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Vehicle $vehicle)
    {
        Gate::authorize('viewAny', VehicleExpense::class);

        try {
            $school = GetSchoolModel();
            if ($vehicle->school_id !== $school?->id) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'Vehicle does not belong to the current school'], 403)
                    : redirect()->back()->with(['error' => 'Vehicle does not belong to the current school']);
            }

            // Build query
            $query = VehicleExpense::with(['vehicle:id,name,registration_number', 'media'])
                ->where('vehicle_id', $vehicle->id)
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $expenses = $query->tableQuery($request);

            if ($request->wantsJson()) {
                return response()->json($expenses);
            }

            return Inertia::render('Transport/Vehicles/Expenses/Index', [ // UI path: resources/js/Pages/Transport/Vehicles/Expenses/Index.vue
                'vehicle' => $vehicle->only('id', 'name', 'registration_number'),
                'expenses' => $expenses,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch vehicle expenses: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch vehicle expenses'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch vehicle expenses']);
        }
    }

    /**
     * Store a newly created vehicle expense in storage.
     *
     * @param StoreVehicleExpenseRequest $request
     * @param Vehicle $vehicle
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreVehicleExpenseRequest $request, Vehicle $vehicle)
    {
        Gate::authorize('create', VehicleExpense::class);

        try {
            $school = GetSchoolModel();
            if ($vehicle->school_id !== $school?->id) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'Vehicle does not belong to the current school'], 403)
                    : redirect()->back()->with(['error' => 'Vehicle does not belong to the current school']);
            }

            $validated = $request->validated();
            $validated['vehicle_id'] = $vehicle->id;
            $vehicleExpense = VehicleExpense::create($validated);

            if ($request->hasFile('file')) {
                try {
                    $media = MediaUploader::source($request->file('file'))
                        ->useCollection('VehicleExpenses')
                        ->upload();
                    $vehicleExpense->attachMedia($media, 'expense-receipt');
                    activity()
                        ->performedOn($vehicleExpense)
                        ->withProperties(['media_id' => $media->id])
                        ->log('Attached media to vehicle expense');
                } catch (\Exception $e) {
                    Log::error('Failed to upload media for vehicle expense: ' . $e->getMessage());
                    throw $e;
                }
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Vehicle expense created successfully'], 201)
                : redirect()->route('vehicle-expenses.index', $vehicle)->with(['success' => 'Vehicle expense created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create vehicle expense: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create vehicle expense'], 500)
                : redirect()->back()->with(['error' => 'Failed to create vehicle expense'])->withInput();
        }
    }

    /**
     * Display the specified vehicle expense.
     *
     * @param Request $request
     * @param Vehicle $vehicle
     * @param VehicleExpense $vehicleExpense
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Vehicle $vehicle, VehicleExpense $vehicleExpense)
    {
        Gate::authorize('view', $vehicleExpense);

        try {
            $vehicleExpense->load(['vehicle:id,name,registration_number', 'media']);
            return response()->json(['vehicleExpense' => $vehicleExpense]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch vehicle expense: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch vehicle expense'], 500);
        }
    }

    /**
     * Update the specified vehicle expense in storage.
     *
     * @param UpdateVehicleExpenseRequest $request
     * @param Vehicle $vehicle
     * @param VehicleExpense $vehicleExpense
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateVehicleExpenseRequest $request, Vehicle $vehicle, VehicleExpense $vehicleExpense)
    {
        Gate::authorize('update', $vehicleExpense);

        try {
            $validated = $request->validated();
            $vehicleExpense->update($validated);

            if ($request->hasFile('file')) {
                try {
                    $vehicleExpense->clearMediaCollection('VehicleExpenses');
                    $media = MediaUploader::source($request->file('file'))
                        ->useCollection('VehicleExpenses')
                        ->upload();
                    $vehicleExpense->attachMedia($media, 'expense-receipt');
                    activity()
                        ->performedOn($vehicleExpense)
                        ->withProperties(['media_id' => $media->id])
                        ->log('Updated media for vehicle expense');
                } catch (\Exception $e) {
                    Log::error('Failed to update media for vehicle expense: ' . $e->getMessage());
                    throw $e;
                }
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Vehicle expense updated successfully'])
                : redirect()->route('vehicle-expenses.index', $vehicle)->with(['success' => 'Vehicle expense updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update vehicle expense: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update vehicle expense'], 500)
                : redirect()->back()->with(['error' => 'Failed to update vehicle expense'])->withInput();
        }
    }

    /**
     * Remove the specified vehicle expense(s) from storage.
     *
     * @param Request $request
     * @param Vehicle $vehicle
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Vehicle $vehicle)
    {
        Gate::authorize('delete', VehicleExpense::class);

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:vehicle_expenses,id',
            ]);

            $school = GetSchoolModel();
            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');

            $query = VehicleExpense::whereIn('id', $ids)->where('vehicle_id', $vehicle->id);
            if ($forceDelete) {
                $query->each(function ($expense) {
                    $expense->clearMediaCollection('VehicleExpenses');
                    $expense->forceDelete();
                });
            } else {
                $query->delete();
            }

            $message = $query->count() ? 'Vehicle expense(s) deleted successfully' : 'No vehicle expenses were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('vehicle-expenses.index', $vehicle)->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete vehicle expenses: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete vehicle expenses'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete vehicle expenses']);
        }
    }

    /**
     * Restore a soft-deleted vehicle expense.
     *
     * @param Request $request
     * @param Vehicle $vehicle
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, Vehicle $vehicle, $id)
    {
        $vehicleExpense = VehicleExpense::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $vehicleExpense);

        try {
            $vehicleExpense->restore();
            return response()->json(['message' => 'Vehicle expense restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore vehicle expense: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore vehicle expense'], 500);
        }
    }
}
