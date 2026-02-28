<?php


use App\Http\Controllers\GuardianController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\UserManagement\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes – User Management & Profiles
|--------------------------------------------------------------------------
|
| All routes in this group are protected by:
| - 'auth' middleware (logged-in users only)
| - 'verified' middleware (email verification if enabled)
| - School scoping via GetSchoolModel() (in controllers/services)
| - Laravel Policies (automatic via $this->authorize() in controllers)
|
| Structure:
| 1. Profile Management (self + admin override) – central personal data
| 2. Role-specific CRUD (students, staff, guardians) – role creation flows
| 3. Central User Management (admin-only) – legacy/bulk user actions
|
*/

// TODO: revisit the verified middleware if email verification is not enforced globally
Route::middleware(['auth', 'verified'])->group(function () {

    // =====================================================================
    // 1. PROFILE MANAGEMENT (Self + Admin Override)
    // =====================================================================
    // Self profile edit (no ID needed – uses auth()->user()->profile)
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    // Update own profile
    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    // Upload own avatar
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])
        ->name('profile.avatar');

    // Admin-only profile routes
    Route::middleware('permission:profile.view-any,')->group(function () {

        // List/search all profiles
        Route::get('/profiles', [ProfileController::class, 'index'])
            ->name('profiles.index');

        // View single profile (personal + linked roles)
        Route::get('/profiles/{profile}', [ProfileController::class, 'show'])
            ->name('profiles.show');

        // Edit any profile
        Route::get('/profiles/{profile}/edit', [ProfileController::class, 'edit'])
            ->name('profiles.edit');

        // Update any profile
        Route::put('/profiles/{profile}', [ProfileController::class, 'update'])
            ->name('profiles.update');

        // Upload avatar for any profile
        Route::post('/profiles/{profile}/avatar', [ProfileController::class, 'uploadAvatar'])
            ->name('profiles.avatar');

        // Create login for any profile
        Route::post('/profiles/{profile}/create-login', [ProfileController::class, 'createLogin'])
            ->name('profiles.create-login')
            ->middleware('can:profile.create-login,App\Models\Profile');

        // Reset password for any profile
        Route::post('/profiles/{profile}/reset-password', [ProfileController::class, 'resetPassword'])
            ->name('profiles.reset-password')
            ->middleware('can:profile.reset-password,App\Models\Profile');

        // Toggle active/inactive status (bulk-capable)
        Route::post('/profiles/toggle-status', [ProfileController::class, 'toggleStatus'])
            ->name('profiles.toggle-status')
            ->middleware('can:profile.toggle-status,App\Models\Profile');

        // Soft-delete (bulk-capable)
        Route::delete('/profiles', [ProfileController::class, 'destroy'])
            ->name('profiles.destroy');

        // Restore soft-deleted (bulk-capable)
        Route::post('/profiles/restore', [ProfileController::class, 'restore'])
            ->name('profiles.restore');

        // Force-delete (permanent, bulk-capable)
        Route::delete('/profiles/force-delete', [ProfileController::class, 'forceDelete'])
            ->name('profiles.force-delete')
            ->middleware('can:profile.force-delete,App\Models\Profile');

        // Merge duplicate profiles
        Route::post('/profiles/{profile}/merge', [ProfileController::class, 'merge'])
            ->name('profiles.merge')
            ->middleware('can:profile.merge,App\Models\Profile');
    });

    // =====================================================================
    // 2. ROLE-SPECIFIC CRUD (Students, Staff, Guardians)
    // =====================================================================
    // Students
    Route::prefix('students')->name('students.')->middleware('can:viewAny,App\Models\Student')->group(function () {
        Route::get('/', [StudentController::class, 'index'])->name('index');
        Route::get('/create', [StudentController::class, 'create'])->name('create');
        Route::post('/', [StudentController::class, 'store'])->name('store');
        Route::get('/{student}', [StudentController::class, 'show'])->name('show');
        Route::get('/{student}/edit', [StudentController::class, 'edit'])->name('edit');
        Route::put('/{student}', [StudentController::class, 'update'])->name('update');
        Route::delete('/{student}', [StudentController::class, 'destroy'])->name('destroy');
    });

    // Staff
    Route::prefix('staff')->name('staff.')->middleware('can:viewAny,App\Models\Staff')->group(function () {
        Route::get('/', [StaffController::class, 'index'])->name('index');
        Route::get('/create', [StaffController::class, 'create'])->name('create');
        Route::post('/', [StaffController::class, 'store'])->name('store');
        Route::get('/{staff}', [StaffController::class, 'show'])->name('show');
        Route::get('/{staff}/edit', [StaffController::class, 'edit'])->name('edit');
        Route::put('/{staff}', [StaffController::class, 'update'])->name('update');
        Route::delete('/{staff}', [StaffController::class, 'destroy'])->name('destroy');
    });

    // Guardians (Parents)
    Route::prefix('guardians')->name('guardians.')->middleware('can:viewAny,App\Models\Guardian')->group(function () {
        Route::get('/', [GuardianController::class, 'index'])->name('index');
        Route::get('/create', [GuardianController::class, 'create'])->name('create');
        Route::post('/', [GuardianController::class, 'store'])->name('store');
        Route::get('/{guardian}', [GuardianController::class, 'show'])->name('show');
        Route::get('/{guardian}/edit', [GuardianController::class, 'edit'])->name('edit');
        Route::put('/{guardian}', [GuardianController::class, 'update'])->name('update');
        Route::delete('/{guardian}', [GuardianController::class, 'destroy'])->name('destroy');
    });
});
