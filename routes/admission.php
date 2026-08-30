<?php

use App\Http\Controllers\Student\AdmissionLifecycleController;
use Illuminate\Support\Facades\Route;

/*
| Phase 3 Admission lifecycle routes
*/

Route::prefix('admissions')->name('admissions.lifecycle.')->group(function () {
    Route::get('offers', [AdmissionLifecycleController::class, 'index'])->name('index');
    Route::get('offers/{admission}', [AdmissionLifecycleController::class, 'show'])->name('show');
    Route::post('from-application/{application}', [AdmissionLifecycleController::class, 'storeFromApplication'])->name('from-application');
    Route::post('direct', [AdmissionLifecycleController::class, 'storeDirect'])->name('direct');
    Route::post('walk-in/{application}', [AdmissionLifecycleController::class, 'storeWalkIn'])->name('walk-in');
    Route::post('{admission}/accept', [AdmissionLifecycleController::class, 'accept'])->name('accept');
    Route::post('{admission}/decline', [AdmissionLifecycleController::class, 'decline'])->name('decline');
    Route::post('{admission}/cancel', [AdmissionLifecycleController::class, 'cancel'])->name('cancel');
    Route::post('{admission}/expire', [AdmissionLifecycleController::class, 'expire'])->name('expire');
    Route::patch('{admission}/deadlines', [AdmissionLifecycleController::class, 'updateDeadlines'])->name('deadlines');
});
