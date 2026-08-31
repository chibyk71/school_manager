<?php

use App\Http\Controllers\Student\EnrollmentController;
use Illuminate\Support\Facades\Route;

/*
| Phase 4 Enrollment lifecycle routes
*/

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
