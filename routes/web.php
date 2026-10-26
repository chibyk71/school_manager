<?php

use App\Http\Controllers\ClassLevelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SchoolController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {

    // Academics
    Route::resource('schools', SchoolController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::delete('/schools', [SchoolController::class, 'destroy'])->name('schools.destroy');
    Route::post('schools/force-delete', [SchoolController::class, 'forceDelete'])
        ->name('schools.force-delete');
    Route::post('schools/restore', [SchoolController::class, 'restore'])
        ->name('schools.restore');
    Route::post('schools/bulk-toggle', [SchoolController::class, 'bulkToggleStatus'])
        ->name('schools.bulk-toggle');
    Route::post('schools/{school}/mark-ready', [SchoolController::class, 'markReady'])->name('schools.mark-ready');
    Route::post('schools/{school}/activate', [SchoolController::class, 'activate'])->name('schools.activate');
    Route::post('schools/{school}/suspend', [SchoolController::class, 'suspend'])->name('schools.suspend');
    Route::post('schools/{school}/reactivate', [SchoolController::class, 'reactivate'])->name('schools.reactivate');
    Route::post('schools/{school}/archive', [SchoolController::class, 'archive'])->name('schools.archive');
    Route::post('schools/{school}/recover', [SchoolController::class, 'recover'])->name('schools.recover');
    Route::get('schools/{school}/readiness', [SchoolController::class, 'readiness'])->name('schools.readiness');


    // Class levels nested under sections
    Route::prefix('sections/{section}/class-levels')
        ->name('class-levels.')
        ->group(function () {
            Route::get('presets', [ClassLevelController::class, 'presets'])->name('presets');
            Route::get('/', [ClassLevelController::class, 'index'])->name('index');
            Route::post('/', [ClassLevelController::class, 'store'])->name('store');
            Route::post('bulk-generate', [ClassLevelController::class, 'bulkGenerate'])->name('bulk-generate');
            Route::patch('reorder', [ClassLevelController::class, 'reorder'])->name('reorder');
            Route::delete('/', [ClassLevelController::class, 'destroy'])->name('destroy');
            Route::post('restore', [ClassLevelController::class, 'restore'])->name('restore');
            Route::delete('force', [ClassLevelController::class, 'forceDelete'])->name('force-delete');
            Route::patch('{classLevel}', [ClassLevelController::class, 'update'])->name('update');
        });
});

// NOTE: remainder of routes restored from d9b9a22 + create/edit; full file in artifacts/routes-web.php.fixed
require __DIR__.'/admission.php';
require __DIR__.'/auth.php';
require __DIR__.'/options.php';
require __DIR__.'/settings.php';
require __DIR__.'/employee_management.php';
require __DIR__.'/promotion.php';
require __DIR__.'/applications.php';
require __DIR__.'/enrollment.php';
require __DIR__.'/lifecycle.php';
require __DIR__.'/address.php';
require __DIR__.'/file_manager.php';
