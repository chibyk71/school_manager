<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\ClassLevelController;
use App\Http\Controllers\ClassSectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\NoticeController;
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
    Route::post('/school', [SchoolController::class, 'store'])->name('school.store');
    Route::post('/school/{school}', [SchoolController::class, 'update'])->name('school.update');
    Route::delete('schools/', [SchoolController::class, 'destroy'])->name('school.destroy');

    Route::get('school-sections', [SchoolSectionController::class, 'index'])->name('sections.index');
    Route::post('school-sections', [SchoolSectionController::class, 'store'])->name('sections.store');
    Route::post('school-sections/{schoolSection}', [SchoolSectionController::class, 'update'])->name('sections.update');
    Route::delete('school-sections/', [SchoolSectionController::class, 'destroy'])->name('sections.destroy');

    Route::get('class-levels', [ClassLevelController::class, 'index'])->name('class-level.index');

    Route::get('class-sections', [ClassSectionController::class, 'index'])->name('class-section.index');

    Route::get('subjects', [SubjectController::class, 'index'])->name('subject.index');

    Route::get('time-table', [TimeTableController::class, 'index'])->name('timetables.index');

    Route::get('exam/schedules', [App\Http\Controllers\Exam\ScheduleController::class, 'index'])->name('exam.schedules.index');

    Route::get('exam/grades', [GradeController::class, 'index'])->name('exam.grades');

    Route::get('assignments', [AssignmentController::class, 'index'])->name('assignment.index');

    Route::get('announcement/notice', [NoticeController::class, 'index'])->name('notice.index');

    Route::get('reports', function () {
        return Inertia::render('Communication/Event');
    });
});

require __DIR__.'/auth.php';

require __DIR__.'/settings.php';
