<?php

use App\Http\Controllers\Academic\ExamController;
use App\Http\Controllers\Academic\ExamResultsController;
use App\Http\Controllers\Academic\ReportCardController;
use App\Http\Controllers\Academic\ScoreEntryController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AssignmentSubmissionController;
use App\Http\Controllers\AttendanceLedgerController;
use App\Http\Controllers\AttendanceSessionController;
use App\Http\Controllers\BookListController;
use App\Http\Controllers\BookOrderController;
use App\Http\Controllers\ClassLevelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DynamicEnumController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventTypeController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\FeeConcessionController;
use App\Http\Controllers\Finance\FeeController;
use App\Http\Controllers\Finance\FeeInstallmentController;
use App\Http\Controllers\Finance\FeeInstallmentDetailController;
use App\Http\Controllers\Finance\FeeTypeController;
use App\Http\Controllers\Finance\FinancialReportController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\TransactionController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\HostelAssignmentController;
use App\Http\Controllers\HostelController;
use App\Http\Controllers\HostelRoomController;
use App\Http\Controllers\LeaveAllocationController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\LessonPlanController;
use App\Http\Controllers\LessonPlanDetailController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\NotificationLogController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\SalaryAddonController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\SalaryStructureController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\Settings\SchoolSectionController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SyllabusController;
use App\Http\Controllers\SyllabusDetailController;
use App\Http\Controllers\Exam\TermResultController;
use App\Http\Controllers\Exam\TermResultDetailController;
use App\Http\Controllers\TimeTableController;
use App\Http\Controllers\TimeTableDetailController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleDocumentController;
use App\Http\Controllers\VehicleExpenseController;
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

// Phase 5 consumer options by definition key (scalar values, school-scoped via GetSchoolModel).
// Admin catalogue/CRUD lives under settings.system.dynamic-enums.* (routes/dynamic_enums.php).
Route::get('/dynamic-enums/{key}/options', [DynamicEnumController::class, 'options'])
    ->where('key', '[a-z0-9_.\-]+')
    ->middleware('auth')
    ->name('dynamic-enums.options');

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
