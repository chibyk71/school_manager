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
use App\Http\Controllers\SelectOptionsController;
use App\Http\Controllers\Settings\PermissionController;
use App\Http\Controllers\Settings\School\RolesController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TimeTableController;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
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

Route::get('/options', [SelectOptionsController::class, '__invoke'])->name('options');

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

    Route::get('school-sections', [SchoolSectionController::class, 'index'])->name('school-section.index');
    Route::post('school-section', [SchoolSectionController::class, 'store'])->name('school-section.store');
    Route::post('school-section/{schoolSection}', [SchoolSectionController::class, 'update'])->name('school-section.update');
    Route::delete('school-section/', [SchoolSectionController::class, 'destroy'])->name('school-section.destroy');

    Route::get('class-levels', [ClassLevelController::class, 'index'])->name('class-level.index');
    Route::post('class-levels', [ClassLevelController::class, 'store'])->name('class-level.store');
    Route::post('class-levels/{classLevel}', [ClassLevelController::class, 'update'])->name('class-level.update');
    Route::delete('class-levels/', [ClassLevelController::class, 'destroy'])->name('class-level.destroy');

    Route::get('class-level/{classLevel?}/class-sections', [ClassSectionController::class, 'index'])->name('class-section.index');
    Route::post('class-section/', [ClassSectionController::class, 'store'])->name('class-section.store');
    Route::post('class-section/{classSection}', [ClassSectionController::class, 'update'])->name('class-section.update');
    Route::delete('class-section/', [ClassSectionController::class, 'destroy'])->name('class-section.destroy');

    Route::get('academic-sessions', [App\Http\Controllers\AcademicSessionController::class, 'index'])->name('academic-session.index');
    Route::post('academic-session', [App\Http\Controllers\AcademicSessionController::class, 'store'])->name('academic-session.store');
    Route::post('academic-session/{academicSession}', [App\Http\Controllers\AcademicSessionController::class, 'update'])->name('academic-session.update');
    Route::delete('academic-session/', [App\Http\Controllers\AcademicSessionController::class, 'destroy'])->name('academic-session.destroy');

    Route::get('terms/{academicSession?}', [App\Http\Controllers\TermController::class, 'index'])->name('term.index');
    Route::post('term', [App\Http\Controllers\TermController::class, 'store'])->name('term.store');
    Route::post('term/{term}', [App\Http\Controllers\TermController::class, 'update'])->name('term.update');
    Route::delete('term/', [App\Http\Controllers\TermController::class, 'destroy'])->name('term.destroy');

    Route::get('subjects', [SubjectController::class, 'index'])->name('subject.index');
    Route::post('subject', [SubjectController::class, 'store'])->name('subject.store');
    Route::post('subject/{subject}', [SubjectController::class, 'update'])->name('subject.update');
    Route::delete('subject/', [SubjectController::class, 'destroy'])->name('subject.destroy');

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
