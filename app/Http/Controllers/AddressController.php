<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Services\AddressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * AddressController v1.0 – Resourceful API Controller for Address Management
 *
 * Purpose & Problems Solved:
 * - Provides a complete RESTful API for managing addresses independently of their owner.
 * - Central entry point for all address-related HTTP requests (list, view, create, update, delete).
 * - Enforces Laravel policy authorization (AddressPolicy) on every action.
 * - Delegates business logic to AddressService – keeps controller thin and focused on HTTP concerns.
 * - Supports filtering by polymorphic owner (addressable_type + addressable_id) – essential for DataTables and modals.
 * - Returns proper JSON responses for API consumption (e.g., from AddressModal.vue direct submit).
 * - Optionally renders Inertia pages if needed in the future (e.g., dedicated address management UI).
 * - Handles soft deletes correctly (destroy = soft delete, no force-delete endpoint exposed yet).
 * - Structured validation via HasAddress trait (called through service).
 * - Comprehensive error handling with meaningful HTTP status codes.
 *
 * Fits into the Address Management Module:
 * - Primary backend exposure for frontend components:
 *   • AddressModal.vue (direct submit mode: POST/PUT /addresses or /addresses/{id})
 *   • AddressManager.vue / DataTables (GET /addresses with filters)
 * - Used when addresses need to be managed separately (e.g., admin address list, bulk operations).
 * - Works in tandem with AddressService (logic), AddressPolicy (security), and HasAddress trait (validation).
 * - Routes: Typically registered as apiResource('addresses', AddressController::class) in api.php.
 *
 * Security Notes:
 * - All actions are gated via Gate::authorize() using AddressPolicy.
 * - Ownership enforced in policy (user can only act on addresses belonging to models they control).
 * - 'address.manage' permission bypasses ownership (for admins).
 *
 * Endpoints Provided:
 * - GET    /addresses              → index()    – list with optional filters
 * - POST   /addresses              → store()     – create new address
 * - GET    /addresses/{address}    → show()      – view single address
 * - PUT    /addresses/{address}    → update()    – update existing
 * - DELETE /addresses/{address}    → destroy()   – soft delete
 *
 * Dependencies:
 * - App\Services\AddressService
 * - App\Models\Address
 * - App\Policies\AddressPolicy (registered in AuthServiceProvider)
 */

class AddressController extends Controller
{
    protected AddressService $service;

    public function __construct(AddressService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of addresses with optional polymorphic filtering.
     *
     * Supports query params:
     * - addressable_type (e.g., App\Models\Student)
     * - addressable_id   (UUID or ID)
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Address::class);

        $query = Address::query();

        // Filter by polymorphic owner if provided
        if ($request->filled('addressable_type') && $request->filled('addressable_id')) {
            $query->where('addressable_type', $request->addressable_type)
                ->where('addressable_id', $request->addressable_id);
        }

        // Optional: include trashed if explicitly requested
        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        // Eager load relations for display
        $addresses = $query->with(['country', 'state', 'city', 'addressable'])->latest()->get();

        // For API consumption (e.g., modals, DataTables)
        return response()->json([
            'data' => $addresses,
        ]);
    }

    /**
     * Store a newly created address.
     *
     * Expects validated data + addressable_type and addressable_id in request.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Address::class);

        $addressableType = $request->input('addressable_type');
        $addressableId = $request->input('addressable_id');

        if (!$addressableType || !$addressableId) {
            return response()->json([
                'message' => 'addressable_type and addressable_id are required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Load the owner model
        $addressable = $addressableType::findOrFail($addressableId);

        // Authorize that user can create address for this owner (policy will check ownership)
        Gate::authorize('create', Address::class);

        $address = $this->service->create(
            addressable: $addressable,
            data: $request->only([
                'country_id',
                'state_id',
                'city_id',
                'address_line_1',
                'address_line_2',
                'landmark',
                'city_text',
                'postal_code',
                'type',
                'latitude',
                'longitude',
                'is_primary',
            ]),
            isPrimary: $request->boolean('is_primary'),
            notify: true
        );

        return response()->json([
            'message' => 'Address created successfully.',
            'data' => $address->load(['country', 'state', 'city']),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified address.
     */
    public function show(Address $address)
    {
        Gate::authorize('view', $address);

        return response()->json([
            'data' => $address->load(['country', 'state', 'city', 'addressable']),
        ]);
    }

    /**
     * Update the specified address.
     */
    public function update(Request $request, Address $address)
    {
        Gate::authorize('update', $address);

        $updatedAddress = $this->service->update(
            address: $address,
            data: $request->only([
                'country_id',
                'state_id',
                'city_id',
                'address_line_1',
                'address_line_2',
                'landmark',
                'city_text',
                'postal_code',
                'type',
                'latitude',
                'longitude',
                'is_primary',
            ]),
            makePrimary: $request->boolean('is_primary'),
            notify: true
        );

        return response()->json([
            'message' => 'Address updated successfully.',
            'data' => $updatedAddress->load(['country', 'state', 'city']),
        ]);
    }

    /**
     * Soft delete the specified address.
     */
    public function destroy(Address $address)
    {
        Gate::authorize('delete', $address);

        $this->service->delete($address, notify: true);

        return response()->json([
            'message' => 'Address deleted successfully.',
        ], Response::HTTP_OK);
    }
}