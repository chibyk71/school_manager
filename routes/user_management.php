<?php

use App\Http\Controllers\{
    GuardianController,
    ProfileController,
    StaffController,
    StudentController,
    UserController
};
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes – User Management & Profiles
|--------------------------------------------------------------------------
|
| All routes below are protected by:
| - auth middleware
| - active school scoping (via your GetSchoolModel() logic)
| - Laravel Policies (automatically applied via controller $this->authorize())
|
*/

// =====================================================================
// 1. PROFILE MANAGEMENT (Self + Admin Override)
// =====================================================================
Route::middleware(['auth'])->group(function () {

    // Self profile edit (no ID needed)
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    // Admin override: edit any profile
    Route::get('/profiles/{profile}/edit', [ProfileController::class, 'edit'])
        ->name('profiles.edit')
        ->where('profile', '[0-9]+');

    // Update profile (self or admin)
    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    // Avatar upload (self or admin)
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])
        ->name('profile.avatar');

    // Admin: List all profiles
    Route::get('/profiles', [ProfileController::class, 'index'])
        ->name('profiles.index')
        ->middleware('can:viewAny,App\Models\Profile');

    // Admin: Merge duplicate users
    Route::post('/profiles/merge', [ProfileController::class, 'merge'])
        ->name('profiles.merge')
        ->middleware('can:merge,App\Models\Profile');
});

// =====================================================================
// 2. STUDENTS
// =====================================================================
Route::prefix('students')->name('students.')->group(function () {
    Route::get('/', [StudentController::class, 'index'])->name('index');
    Route::get('/create', [StudentController::class, 'create'])->name('create');
    Route::post('/', [StudentController::class, 'store'])->name('store');
    Route::get('/{student}', [StudentController::class, 'show'])->name('show');
    Route::get('/{student}/edit', [StudentController::class, 'edit'])->name('edit');
    Route::put('/{student}', [StudentController::class, 'update'])->name('update');
    Route::delete('/{student}', [StudentController::class, 'destroy'])->name('destroy');
})->middleware(['auth', 'can:viewAny,App\Models\Academic\Student']);

// Bulk delete (optional)
Route::delete('/students/bulk-delete', [StudentController::class, 'destroy'])
    ->name('students.bulk-delete');

// =====================================================================
// 3. STAFF
// =====================================================================
Route::prefix('staff')->name('staff.')->group(function () {
    Route::get('/', [StaffController::class, 'index'])->name('index');
    Route::get('/create', [StaffController::class, 'create'])->name('create');
    Route::post('/', [StaffController::class, 'store'])->name('store');
    Route::get('/{staff}', [StaffController::class, 'show'])->name('show');
    Route::get('/{staff}/edit', [StaffController::class, 'edit'])->name('edit');
    Route::put('/{staff}', [StaffController::class, 'update'])->name('update');
    Route::delete('/{staff}', [StaffController::class, 'destroy'])->name('destroy');

    // Restore soft-deleted
    Route::post('/{id}/restore', [StaffController::class, 'restore'])
        ->name('restore')
        ->where('id', '[0-9]+');
})->middleware(['auth', 'can:viewAny,App\Models\Employee\Staff']);

// Bulk operations
Route::delete('/staff/bulk-delete', [StaffController::class, 'destroy'])
    ->name('staff.bulk-delete');

// =====================================================================
// 4. GUARDIANS (PARENTS)
// =====================================================================
Route::prefix('guardians')->name('guardians.')->group(function () {
    Route::get('/', [GuardianController::class, 'index'])->name('index');
    Route::get('/create', [GuardianController::class, 'create'])->name('create');
    Route::post('/', [GuardianController::class, 'store'])->name('store');
    Route::get('/{guardian}', [GuardianController::class, 'show'])->name('show');
    Route::get('/{guardian}/edit', [GuardianController::class, 'edit'])->name('edit');
    Route::put('/{guardian}', [GuardianController::class, 'update'])->name('update');
    Route::delete('/{guardian}', [GuardianController::class, 'destroy'])->name('destroy');
})->middleware(['auth', 'can:viewAny,App\Models\Guardian']);

// =====================================================================
// 5. CENTRAL USER MANAGEMENT (Admin Only)
// =====================================================================
Route::prefix('users')->name('users.')->middleware(['auth'])->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}', [UserController::class, 'show'])->name('show');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');

    // Bulk actions
    Route::post('/bulk-activate', [UserController::class, 'bulkActivate'])->name('bulk.activate');
    Route::post('/bulk-deactivate', [UserController::class, 'bulkDeactivate'])->name('bulk.deactivate');
    Route::delete('/bulk-delete', [UserController::class, 'bulkDelete'])->name('bulk.delete');

    // Password reset by admin
    Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])
        ->name('reset-password');
});
