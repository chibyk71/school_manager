<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\ClassLevelController;
use App\Http\Controllers\ClassSectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SchoolSectionController;
use App\Http\Controllers\Settings\PermissionController;
use App\Http\Controllers\Settings\School\RolesController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TimeTableController;
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

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, '__invoke'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // roles and permissions (User Management)
    Route::get('/roles', [RolesController::class, 'index'])->name('roles.index');

    Route::get('/permission/{role}', [PermissionController::class, 'index'])->name('permission');

    Route::get('/students', [StudentController::class, 'index'])->name('student.index');

    Route::get('/teachers', [TeacherController::class, 'index'])->name('teacher.index');

    Route::get('/schools', [SchoolController::class, 'index'])->name('school.index');

    Route::get('school-sections', [SchoolSectionController::class, 'index'])->name('sections.index');

    Route::get('class-levels', [ClassLevelController::class, 'index'])->name('class-level.index');

    Route::get('class-sections', [ClassSectionController::class, 'index'])->name('class-level.index');

    Route::get('subjects', [SubjectController::class, 'index'])->name('class-level.index');

    Route::get('time-table', [TimeTableController::class, 'index'])->name('timetables.index');

    Route::get('exam/schedules', [App\Http\Controllers\Exam\ScheduleController::class, 'index'])->name('exam.schedules.index');

    Route::get('exam/grades', [GradeController::class, 'index'])->name('exam.grades');

    Route::get('assignments', [AssignmentController::class, 'index'])->name('assignment.index');
});

require __DIR__.'/auth.php';

require __DIR__.'/settings.php';
