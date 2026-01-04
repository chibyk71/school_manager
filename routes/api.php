<?php

use App\Http\Controllers\AddressController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Ensure these routes are within your authenticated group
Route::middleware(['auth:sanctum'])->group(function () {
    // Full RESTful resource for addresses
    Route::apiResource('addresses', AddressController::class);
});