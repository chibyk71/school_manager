<?php

use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DepartmentRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {

    // Main Departments resource (standard CRUD + extras)
    Route::resource('departments', DepartmentController::class)
        ->except(['show']) // We don't need a dedicated show page yet (can use modal/details tab)
        ->names([
            'index'   => 'departments.index',
            'create'  => 'departments.create',
            'store'   => 'departments.store',
            'edit'    => 'departments.edit',
            'update'  => 'departments.update',
            'destroy' => 'departments.destroy',
        ]);

    // Extra member routes for Department
    Route::post('departments/{department}/assign-role', [DepartmentController::class, 'assignRole'])
        ->name('departments.assign-role');

    Route::get('departments/{department}/users', [DepartmentController::class, 'users'])
        ->name('departments.users');

    Route::get('departments/{department}/roles', [DepartmentController::class, 'roles'])
        ->name('departments.roles');

    // Soft-delete extras (if not using resource)
    Route::post('departments/{id}/restore', [DepartmentController::class, 'restore'])
        ->name('departments.restore');

    Route::delete('departments/{id}/force', [DepartmentController::class, 'forceDestroy'])
        ->name('departments.force-delete');

    // Optional: Full resource for managing custom DepartmentRoles (if you want a separate page)
    Route::resource('department-roles', DepartmentRoleController::class)
        ->names('department-roles');
});
