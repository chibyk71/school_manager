<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleDocumentRequest;
use App\Http\Requests\UpdateVehicleDocumentRequest;
use App\Models\Transport\Vehicle\Vehicle;
use App\Models\Transport\Vehicle\VehicleDocument;
use FarhanShares\MediaMan\MediaUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing VehicleDocument resources in a multi-tenant school management system.
 */
class VehicleDocumentController extends Controller
{
    /**
     * Display a listing of vehicle documents with dynamic querying.
     *
     * @param Request $request
     * @param Vehicle $vehicle
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Vehicle $vehicle)
    {
        Gate::authorize('viewAny', VehicleDocument::class);

        try {
            $school = GetSchoolModel();
            if ($vehicle->school_id !== $school?->id) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'Vehicle does not belong to the current school'], 403)
                    : redirect()->back()->with(['error' => 'Vehicle does not belong to the current school']);
            }

            // Build query
            $query = VehicleDocument::with(['vehicle:id,name,registration_number'])
                ->where('vehicle_id', $vehicle->id)
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $documents = $query->tableQuery($request);

            if ($request->wantsJson()) {
                return response()->json($documents);
            }

            return Inertia::render('Transport/Vehicles/Documents/Index', [ // UI path: resources/js/Pages/Transport/Vehicles/Documents/Index.vue
                'vehicle' => $vehicle->only('id', 'name', 'registration_number'),
                'documents' => $documents,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch vehicle documents: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch vehicle documents'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch vehicle documents']);
        }
    }

    /**
     * Store a newly created vehicle document in storage.
     *
     * @param StoreVehicleDocumentRequest $request
     * @param Vehicle $vehicle
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreVehicleDocumentRequest $request, Vehicle $vehicle)
    {
        Gate::authorize('create', VehicleDocument::class);

        try {
            $school = GetSchoolModel();
            if ($vehicle->school_id !== $school?->id) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'Vehicle does not belong to the current school'], 403)
                    : redirect()->back()->with(['error' => 'Vehicle does not belong to the current school']);
            }

            $validated = $request->validated();
            $validated['vehicle_id'] = $vehicle->id;
            $vehicleDocument = VehicleDocument::create($validated);

            if ($request->hasFile('file')) {
                try {
                    $media = MediaUploader::source($request->file('file'))
                        ->useCollection('VehicleDocuments')
                        ->upload();
                    $vehicleDocument->attachMedia($media);
                    activity()
                        ->performedOn($vehicleDocument)
                        ->withProperties(['media_id' => $media->id])
                        ->log('Attached media to vehicle document');
                } catch (\Exception $e) {
                    Log::error('Failed to upload media for vehicle document: ' . $e->getMessage());
                    throw $e;
                }
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Vehicle document created successfully'], 201)
                : redirect()->route('vehicle-documents.index', $vehicle)->with(['success' => 'Vehicle document created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create vehicle document: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create vehicle document'], 500)
                : redirect()->back()->with(['error' => 'Failed to create vehicle document'])->withInput();
        }
    }

    /**
     * Display the specified vehicle document.
     *
     * @param Request $request
     * @param Vehicle $vehicle
     * @param VehicleDocument $vehicleDocument
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Vehicle $vehicle, VehicleDocument $vehicleDocument)
    {
        Gate::authorize('view', $vehicleDocument);

        try {
            $vehicleDocument->load(['vehicle:id,name,registration_number']);
            return response()->json(['vehicleDocument' => $vehicleDocument]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch vehicle document: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch vehicle document'], 500);
        }
    }

    /**
     * Update the specified vehicle document in storage.
     *
     * @param UpdateVehicleDocumentRequest $request
     * @param Vehicle $vehicle
     * @param VehicleDocument $vehicleDocument
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateVehicleDocumentRequest $request, Vehicle $vehicle, VehicleDocument $vehicleDocument)
    {
        Gate::authorize('update', $vehicleDocument);

        try {
            $validated = $request->validated();
            $vehicleDocument->update($validated);

            if ($request->hasFile('file')) {
                try {
                    // Remove existing media in the collection
                    $vehicleDocument->clearMediaChannel('VehicleDocuments');
                    $media = MediaUploader::source($request->file('file'))
                        ->useCollection('VehicleDocuments')
                        ->upload();
                    $vehicleDocument->attachMedia($media);
                    activity()
                        ->performedOn($vehicleDocument)
                        ->withProperties(['media_id' => $media->id])
                        ->log('Updated media for vehicle document');
                } catch (\Exception $e) {
                    Log::error('Failed to update media for vehicle document: ' . $e->getMessage());
                    throw $e;
                }
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Vehicle document updated successfully'])
                : redirect()->route('vehicle-documents.index', $vehicle)->with(['success' => 'Vehicle document updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update vehicle document: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update vehicle document'], 500)
                : redirect()->back()->with(['error' => 'Failed to update vehicle document'])->withInput();
        }
    }

    /**
     * Remove the specified vehicle document(s) from storage.
     *
     * @param Request $request
     * @param Vehicle $vehicle
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Vehicle $vehicle)
    {
        Gate::authorize('delete', VehicleDocument::class);

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:vehicle_documents,id',
            ]);

            $school = GetSchoolModel();
            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');

            $query = VehicleDocument::whereIn('id', $ids)->where('vehicle_id', $vehicle->id);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? 'Vehicle document(s) deleted successfully' : 'No vehicle documents were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('vehicle-documents.index', $vehicle)->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete vehicle documents: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete vehicle documents'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete vehicle documents']);
        }
    }

    /**
     * Restore a soft-deleted vehicle document.
     *
     * @param Request $request
     * @param Vehicle $vehicle
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, Vehicle $vehicle, $id)
    {
        $vehicleDocument = VehicleDocument::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $vehicleDocument);

        try {
            $vehicleDocument->restore();
            return response()->json(['message' => 'Vehicle document restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore vehicle document: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore vehicle document'], 500);
        }
    }
}
