<?php

use App\Http\Controllers\Student\LifecycleOperationsController;
use App\Http\Controllers\Student\LifecycleReportsController;
use Illuminate\Support\Facades\Route;

/**
 * Phase 7 — Lifecycle operational views & reports.
 * School isolation is enforced in the controllers/services.
 */
Route::middleware(['auth'])->prefix('lifecycle')->name('lifecycle.')->group(function () {
    Route::get('needs-attention', [LifecycleOperationsController::class, 'needsAttention'])
        ->name('needs-attention');
    Route::get('upcoming-deadlines', [LifecycleOperationsController::class, 'upcomingDeadlines'])
        ->name('upcoming-deadlines');
    Route::get('recently-completed', [LifecycleOperationsController::class, 'recentlyCompleted'])
        ->name('recently-completed');
    Route::get('dashboard-summary', [LifecycleOperationsController::class, 'dashboardSummary'])
        ->name('dashboard-summary');

    Route::get('reports', [LifecycleReportsController::class, 'index'])->name('reports');
    Route::get('reports/export', [LifecycleReportsController::class, 'export'])->name('reports.export');
});
