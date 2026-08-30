<?php

use App\Http\Controllers\Student\ApplicationController;
use App\Http\Controllers\Student\PublicApplicationController;
use Illuminate\Support\Facades\Route;

/*
| Student Lifecycle – Application (Phase 2)
*/

// Public (no auth)
Route::prefix('apply')->name('public.apply.')->group(function () {
    Route::get('/', [PublicApplicationController::class, 'show'])->name('show');
    Route::post('/', [PublicApplicationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('store');
    Route::get('/status', [PublicApplicationController::class, 'status'])->name('status');
});

// Staff (auth + school context assumed by app middleware stack)
Route::middleware(['auth'])->prefix('applications')->name('applications.')->group(function () {
    Route::get('/', [ApplicationController::class, 'index'])->name('index');
    Route::post('/', [ApplicationController::class, 'store'])->name('store');
    Route::get('/{application}', [ApplicationController::class, 'show'])->name('show');
    Route::post('/{application}/review', [ApplicationController::class, 'beginReview'])->name('review');
    Route::post('/{application}/approve', [ApplicationController::class, 'approve'])->name('approve');
    Route::post('/{application}/reject', [ApplicationController::class, 'reject'])->name('reject');
    Route::delete('/{application}', [ApplicationController::class, 'destroy'])->name('destroy');
});
