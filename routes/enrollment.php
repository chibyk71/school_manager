<?php

use App\Http\Controllers\Student\EnrollmentController;
use App\Http\Controllers\Student\PlacementController;
use Illuminate\Support\Facades\Route;

Route::prefix('enrollments')->name('enrollments.')->group(function () {
    Route::get('/', [EnrollmentController::class, 'index'])->name('index');
    Route::get('profiles/search', [EnrollmentController::class, 'searchProfiles'])->name('profiles.search');
    Route::post('/', [EnrollmentController::class, 'store'])->name('store');
    Route::get('{enrollment}', [EnrollmentController::class, 'show'])->name('show');
    Route::patch('{enrollment}/biodata', [EnrollmentController::class, 'updateBiodata'])->name('biodata');
    Route::get('{enrollment}/readiness', [EnrollmentController::class, 'readiness'])->name('readiness');
    Route::post('{enrollment}/finalize', [EnrollmentController::class, 'finalize'])->name('finalize');
    Route::post('{enrollment}/requirements/{instance}/satisfy', [EnrollmentController::class, 'satisfyRequirement'])
        ->name('requirements.satisfy');
    Route::post('{enrollment}/requirements/{instance}/waive', [EnrollmentController::class, 'waiveRequirement'])
        ->name('requirements.waive');
});

Route::prefix('placements')->name('placements.')->group(function () {
    Route::post('allocate/{enrollment}', [PlacementController::class, 'allocate'])->name('allocate');
    Route::post('manual/{student}', [PlacementController::class, 'manual'])->name('manual');
    Route::post('change-section/{student}', [PlacementController::class, 'changeSection'])->name('change-section');
    Route::post('change-class/{student}', [PlacementController::class, 'changeClass'])->name('change-class');
    Route::post('regenerate-registration/{student}', [PlacementController::class, 'regenerateRegistration'])->name('regenerate');
    Route::get('history/{student}', [PlacementController::class, 'history'])->name('history');
});
