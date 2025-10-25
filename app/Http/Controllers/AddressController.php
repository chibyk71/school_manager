<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Models\Address;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing addresses in a multi-tenant school management system.
 */
class AddressController extends Controller
{
    /**
     * Display a listing of addresses.
     *
     * Retrieves addresses for the active school using dynamic table querying.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Inertia\Response The Inertia response with addresses data.
     */
    public function index(Request $request): \Inertia\Response
    {
        try {
            Gate::authorize('viewAny', Address::class);

            $school = GetSchoolModel();

            // Use HasTableQuery for dynamic querying, searching, sorting, and pagination
            $addresses = Address::withTrashed() // Include soft-deleted addresses for potential restore
                ->where('school_id', $school?->id)
                ->tableQuery($request);

            // Fetch all countries
            $countries = Country::select('id', 'name', 'code')->get();

            return Inertia::render('Settings/Addresses/Index', [ // UI path: resources/js/Pages/Settings/Addresses/Index.vue
                'addresses' => $addresses,
                'countries' => $countries
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch addresses: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load addresses.');
        }
    }

    /**
     * Store a newly created address.
     *
     * Creates an address for the active school with validated data.
     *
     * @param StoreAddressRequest $request The validated request.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     */
    public function store(StoreAddressRequest $request): \Illuminate\Http\RedirectResponse
    {
        try {
            Gate::authorize('create', Address::class);

            $validated = $request->validated();
            $school = GetSchoolModel();

            $validated['school_id'] = $school?->id;
            $address = Address::create($validated);

            // Optional: Notify admins (e.g., via email or broadcast)
            // \Illuminate\Support\Facades\Notification::send($adminUsers, new AddressCreated($address));

            return redirect()
                ->route('addresses.index')
                ->with('success', 'Address created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create address: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create address: ' . $e->getMessage());
        }
    }

    /**
     * Display a specific address.
     *
     * Retrieves an address if it belongs to the active school.
     *
     * @param Address $address The address to display.
     * @return \Illuminate\Http\JsonResponse The JSON response with address data.
     */
    public function show(Address $address): \Illuminate\Http\JsonResponse
    {
        try {
            Gate::authorize('view', $address);

            $school = GetSchoolModel();
            if ($address->school_id !== $school?->id) {
                abort(403, 'Unauthorized access to address.');
            }

            return response()->json([
                'address' => $address->load('country'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch address: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch address.'], 500);
        }
    }

    /**
     * Update an existing address.
     *
     * Updates an address with validated data, ensuring it belongs to the active school.
     *
     * @param UpdateAddressRequest $request The validated request.
     * @param Address $address The address to update.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     */
    public function update(UpdateAddressRequest $request, Address $address): \Illuminate\Http\RedirectResponse
    {
        try {
            Gate::authorize('update', $address);

            $school = GetSchoolModel();
            if ($address->school_id !== $school?->id) {
                abort(403, 'Unauthorized access to address.');
            }

            $validated = $request->validated();
            if ($validated['is_primary'] ?? false) {
                Address::where('addressable_id', $address->addressable_id)
                    ->where('addressable_type', $address->addressable_type)
                    ->where('school_id', $school?->id)
                    ->where('id', '!=', $address->id)
                    ->update(['is_primary' => false]);
            }

            $address->update($validated);

            // Optional: Notify admins
            // \Illuminate\Support\Facades\Notification::send($adminUsers, new AddressUpdated($address));

            return redirect()
                ->route('addresses.index')
                ->with('success', 'Address updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update address: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update address: ' . $e->getMessage());
        }
    }

    /**
     * Delete one or more addresses (soft delete).
     *
     * Supports bulk deletion by IDs for the active school.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\Http\JsonResponse The JSON response indicating success or failure.
     */
    public function destroy(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            Gate::authorize('delete', Address::class);

            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:addresses,id',
            ]);

            $school = GetSchoolModel();

            Address::where('school_id', $school?->id)
                ->whereIn('id', $validated['ids'])
                ->delete();

            // Optional: Notify admins
            // \Illuminate\Support\Facades\Notification::send($adminUsers, new AddressesDeleted($validated['ids']));

            return response()->json(['message' => 'Address(es) deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to delete addresses: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete addresses.', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Restore one or more soft-deleted addresses.
     *
     * Supports bulk restoration by IDs for the active school.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\Http\JsonResponse The JSON response indicating success or failure.
     */
    public function restore(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            Gate::authorize('restore', Address::class);

            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:addresses,id',
            ]);

            $school = GetSchoolModel();

            Address::withTrashed()
                ->where('school_id', $school?->id)
                ->whereIn('id', $validated['ids'])
                ->restore();

            // Optional: Notify admins
            // \Illuminate\Support\Facades\Notification::send($adminUsers, new AddressesRestored($validated['ids']));

            return response()->json(['message' => 'Address(es) restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore addresses: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore addresses.', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Permanently delete one or more addresses.
     *
     * Supports bulk force deletion by IDs for the active school.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\Http\JsonResponse The JSON response indicating success or failure.
     */
    public function forceDestroy(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            Gate::authorize('forceDelete', Address::class);

            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:addresses,id',
            ]);

            $school = GetSchoolModel();

            Address::withTrashed()
                ->where('school_id', $school?->id)
                ->whereIn('id', $validated['ids'])
                ->forceDelete();

            // Optional: Notify admins
            // \Illuminate\Support\Facades\Notification::send($adminUsers, new AddressesForceDeleted($validated['ids']));

            return response()->json(['message' => 'Address(es) permanently deleted']);
        } catch (\Exception $e) {
            Log::error('Failed to force delete addresses: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to permanently delete addresses.', 'details' => $e->getMessage()], 500);
        }
    }
}
