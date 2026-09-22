<?php

use App\Http\Controllers\AddressController;
use Illuminate\Support\Facades\Route;

/**
 * Address Module Phase 4 — owner-scoped Address HTTP API.
 *
 * Authorization is performed against the resolved owner (view / update).
 * No AddressPolicy or address.* permissions.
 */
Route::middleware(['auth'])->group(function () {
    Route::get('/addresses/{owner}/{ownerId}', [AddressController::class, 'index'])
        ->name('addresses.index');
    Route::post('/addresses/{owner}/{ownerId}', [AddressController::class, 'store'])
        ->name('addresses.store');
    Route::patch('/addresses/{owner}/{ownerId}/{address}', [AddressController::class, 'update'])
        ->name('addresses.update');
    Route::delete('/addresses/{owner}/{ownerId}/{address}', [AddressController::class, 'destroy'])
        ->name('addresses.destroy');
    Route::post('/addresses/{owner}/{ownerId}/{address}/primary', [AddressController::class, 'setPrimary'])
        ->name('addresses.set-primary');
    Route::delete('/addresses/{owner}/{ownerId}/{address}/primary', [AddressController::class, 'unsetPrimary'])
        ->name('addresses.unset-primary');
});
