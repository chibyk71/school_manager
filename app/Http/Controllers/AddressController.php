<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     * TODO: Implement this method to return a list of addressesand a page to view the address.
     */
    public function index()
    {

    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAddressRequest $request)
    {
        permitted(['create-address', 'manage-address']);
        $address = Address::create($request->validated());

        return response()->json([
            'message' => 'Address created successfully',
            'address' => $address,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Address $address)
    {
        permitted(['view-address', 'manage-address']);
        return response()->json([
            'address' => $address,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAddressRequest $request, Address $address)
    {
        permitted(['update-address', 'manage-address']);

        $address->update($request->validated());
        // If the address is primary, set all other addresses of the same addressable to non-primary
        return response()->json([
            'message' => 'Address updated successfully',
            'address' => $address,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        permitted(['delete-address', 'manage-address']);
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:class_sections,id',
        ]);

        try {
            Address::destroy($request->ids);
            return response()->json(['message' => 'Address(es) deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete Address(es)', 'details' => $e->getMessage()], 500);
        }
    }
}
