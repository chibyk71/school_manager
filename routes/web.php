<?php

use App\Http\Controllers\AcademicSessionController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AssignmentSubmissionController;
use App\Http\Controllers\AttendanceLedgerController;
use App\Http\Controllers\AttendanceSessionController;
use App\Http\Controllers\BookListController;
use App\Http\Controllers\BookOrderController;
use App\Http\Controllers\ClassLevelController;
use App\Http\Controllers\ClassSectionController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DepartmentRoleController;
use App\Http\Controllers\DynamicEnumController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventTypeController;
use App\Http\Controllers\Exam\AssessmentController;
use App\Http\Controllers\Exam\AssessmentResultController;
use App\Http\Controllers\Exam\AssessmentScheduleController;
use App\Http\Controllers\Exam\AssessmentTypeController;
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
use App\Http\Controllers\GradeController;
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
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionBatchController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\SalaryAddonController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\SalaryStructureController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SchoolSectionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SyllabusController;
use App\Http\Controllers\SyllabusDetailController;
use App\Http\Controllers\TermController;
use App\Http\Controllers\Exam\TermResultController;
use App\Http\Controllers\Exam\TermResultDetailController;
use App\Http\Controllers\TimeTableController;
use App\Http\Controllers\TimeTableDetailController;
use App\Http\Controllers\UserController;
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

Route::get('/dynamic-enums/options/{appliesTo}/{name}', [DynamicEnumController::class, 'options'])
    ->name('dynamic-enums.options');

Route::middleware('auth')->group(function () {

    Route::prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::resource('dynamic-enums', DynamicEnumController::class)
                ->except(['show']) // No dedicated show page needed
                ->names([
                    'index' => 'dynamic-enums.index',
                    'create' => 'dynamic-enums.create',
                    'store' => 'dynamic-enums.store',
                    'edit' => 'dynamic-enums.edit',
                    'update' => 'dynamic-enums.update',
                    'destroy' => 'dynamic-enums.destroy',
                ]);
        });

    // Academics
    Route::resource('schools', SchoolController::class);
    Route::delete('/schools', [SchoolController::class, 'destroy'])->name('schools.destroy');
    Route::post('schools/force-delete', [SchoolController::class, 'forceDelete'])
        ->name('schools.force-delete');
    Route::post('schools/restore', [SchoolController::class, 'restore'])
        ->name('schools.restore');
    Route::post('schools/bulk-toggle', [SchoolController::class, 'bulkToggleStatus'])
        ->name('schools.bulk-toggle');

    Route::resource('sections', SchoolSectionController::class);


    // User Management
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users/email', [UserController::class, 'updateEmail'])->name('users.email');
    Route::post('/users/set-password', [UserController::class, 'setPassword'])->name('users.set-password');
    Route::post('/users/status', [UserController::class, 'toggleStatus'])->name('users.status');
    Route::post('/users/delete', [UserController::class, 'destroy'])->name('users.delete');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar.upload');
    // Admin can edit any profile
    Route::get('/profile/{profile}/edit', [ProfileController::class, 'edit'])
        ->name('profile.edit.override');
});

// Exams
Route::resource('exam.assessment', AssessmentController::class);
Route::resource('exam.assessment-results', AssessmentResultController::class);
Route::resource('exam.assessment-schedules', AssessmentScheduleController::class);
Route::resource('exam.assessment-types', AssessmentTypeController::class);
Route::resource('exam.term-results', TermResultController::class);
Route::resource('exam.term-results.details', TermResultDetailController::class);

// guardian manage
Route::resource('guardians', GuardianController::class);
Route::resource('staff', StaffController::class);
Route::resource('student', StudentController::class);

// Academics
Route::resource('academic.session', AcademicSessionController::class);
Route::delete('/', [AcademicSessionController::class, 'destroy'])->name('academic.session.destroy');
Route::post('/{session}/current', [AcademicSessionController::class, 'setCurrent'])->name('academic.session.setCurrent');

Route::resource('promotions', PromotionBatchController::class)
    ->only(['index', 'review']);

Route::post('promotions/{batch}/approve', [PromotionBatchController::class, 'approve'])
    ->name('promotions.approve');

Route::post('promotions/{batch}/reject', [PromotionBatchController::class, 'reject'])
    ->name('promotions.reject');

Route::post('promotions/{batch}/execute', [PromotionBatchController::class, 'execute'])
    ->name('promotions.execute');

Route::post('promotions/{batch}/bulk-override', [PromotionBatchController::class, 'bulkOverride'])
    ->name('promotions.bulk-override');

Route::resource('subjects', SubjectController::class);
Route::resource('grades', GradeController::class);
Route::resource('class-levels', ClassLevelController::class);
Route::resource('class-sections', ClassSectionController::class);
Route::resource('timetables', TimeTableController::class);
Route::resource('timetable-details', TimeTableDetailController::class);
Route::resource('assignments', AssignmentController::class);
Route::resource('terms', TermController::class);
Route::delete('/', [TermController::class, 'destroy'])->name('terms.destroy');
Route::post('/{term}/active', [TermController::class, 'setActive'])->name('terms.setActive');
// Resource routes for assignment submissions
Route::resource('assignment-submissions', AssignmentSubmissionController::class);
Route::delete('assignment-submissions', [AssignmentSubmissionController::class, 'destroy'])->name('assignment-submissions.destroy');
Route::post('assignment-submissions/restore', [AssignmentSubmissionController::class, 'restore'])->name('assignment-submissions.restore');
// Resource routes for book lists
Route::resource('book-lists', BookListController::class);
Route::delete('book-lists', [BookListController::class, 'destroy'])->name('book-lists.bulk.destroy');
Route::post('book-lists/restore', [BookListController::class, 'restore'])->name('book-lists.restore');
// Resource routes for book orders
Route::resource('book-orders', BookOrderController::class);
Route::delete('book-orders', [BookOrderController::class, 'destroy'])->name('book-orders.destroy');
Route::post('book-orders/restore', [BookOrderController::class, 'restore'])->name('book-orders.restore');
// Resource routes for lesson plans
Route::resource('lesson-plans', LessonPlanController::class);
Route::delete('lesson-plans', [LessonPlanController::class, 'destroy'])->name('lesson-plans.destroy');
Route::post('lesson-plans/restore', [LessonPlanController::class, 'restore'])->name('lesson-plans.restore');
// Resource routes for lesson plan details
Route::resource('lesson-plan-details', LessonPlanDetailController::class);
Route::delete('lesson-plan-details', [LessonPlanDetailController::class, 'destroy'])->name('lesson-plan-details.destroy');
Route::post('lesson-plan-details/restore', [LessonPlanDetailController::class, 'restore'])->name('lesson-plan-details.restore');
Route::post('lesson-plan-details/{lessonPlanDetail}/submit-approval', [LessonPlanDetailController::class, 'submitApproval'])->name('lesson-plan-details.submit-approval');
Route::post('lesson-plan-details/{lessonPlanDetail}/approve', [LessonPlanDetailController::class, 'approve'])->name('lesson-plan-details.approve');
Route::post('lesson-plan-details/{lessonPlanDetail}/reject', [LessonPlanDetailController::class, 'reject'])->name('lesson-plan-details.reject');
// Resource routes for syllabi
Route::resource('syllabi', SyllabusController::class);
Route::delete('syllabi', [SyllabusController::class, 'destroy'])->name('syllabi.destroy');
Route::post('syllabi/restore', [SyllabusController::class, 'restore'])->name('syllabi.restore');
Route::post('syllabi/{syllabus}/submit-approval', [SyllabusController::class, 'submitApproval'])->name('syllabi.submit-approval');
Route::post('syllabi/{syllabus}/approve', [SyllabusController::class, 'approve'])->name('syllabi.approve');
Route::post('syllabi/{syllabus}/reject', [SyllabusController::class, 'reject'])->name('syllabi.reject');
// Resource routes for syllabus details
Route::resource('syllabus-details', SyllabusDetailController::class);
Route::delete('syllabus-details', [SyllabusDetailController::class, 'destroy'])->name('syllabus-details.destroy');
Route::post('syllabus-details/restore', [SyllabusDetailController::class, 'restore'])->name('syllabus-details.restore');
Route::post('syllabus-details/{syllabusDetail}/submit-approval', [SyllabusDetailController::class, 'submitApproval'])->name('syllabus-details.submit-approval');
Route::post('syllabus-details/{syllabusDetail}/approve', [SyllabusDetailController::class, 'approve'])->name('syllabus-details.approve');
Route::post('syllabus-details/{syllabusDetail}/reject', [SyllabusDetailController::class, 'reject'])->name('syllabus-details.reject');
// Resource routes for attendance ledgers
Route::resource('attendance-ledgers', AttendanceLedgerController::class);
Route::delete('attendance-ledgers', [AttendanceLedgerController::class, 'destroy'])->name('attendance-ledgers.destroy');
Route::post('attendance-ledgers/restore', [AttendanceLedgerController::class, 'restore'])->name('attendance-ledgers.restore');
Route::post('attendance/bulk-mark', [AttendanceLedgerController::class, 'bulkMark'])->name('attendance.bulk-mark');
// Resource routes for attendance sessions
Route::resource('attendance-sessions', AttendanceSessionController::class);
Route::delete('attendance-sessions', [AttendanceSessionController::class, 'destroy'])->name('attendance-sessions.destroy');
Route::post('attendance-sessions/restore', [AttendanceSessionController::class, 'restore'])->name('attendance-sessions.restore');
// Resource routes for admissions
Route::resource('admissions', AdmissionController::class);
Route::delete('admissions/bulk', [AdmissionController::class, 'destroy'])->name('admissions.bulk.destroy');
Route::post('admissions/restore', [AdmissionController::class, 'restore'])->name('admissions.restore');

Route::resource('leave-allocations', LeaveAllocationController::class);
Route::resource('leave-requests', LeaveRequestController::class);
Route::resource('leave-types', LeaveTypeController::class);
Route::resource('payrolls', PayrollController::class);
Route::resource('salaries', SalaryController::class);
Route::resource('salary-structures', SalaryStructureController::class);
Route::resource('salary-addons', SalaryAddonController::class);

// finance management
Route::group(['prefix' => 'finance'], function () {
    Route::resource('expenses', ExpenseController::class);

    Route::prefix('fees')->name('fees.')->middleware(['auth'])->group(function () {
        Route::resource('/', FeeController::class)->parameters(['' => 'fee']);
        Route::delete('/', [FeeController::class, 'destroy'])->name('destroy');
        Route::post('/{fee}/restore', [FeeController::class, 'restore'])->name('restore');
    });

    Route::prefix('fee-types')->name('fee-types.')->middleware(['auth'])->group(function () {
        Route::resource('/', FeeTypeController::class)->parameters(['' => 'feeType']);
        Route::delete('/', [FeeTypeController::class, 'destroy'])->name('destroy');
        Route::post('/{feeType}/restore', [FeeTypeController::class, 'restore'])->name('restore');
    });

    Route::prefix('transactions')->name('transactions.')->middleware(['auth'])->group(function () {
        Route::resource('/', TransactionController::class)->parameters(['' => 'transaction']);
        Route::delete('/', [TransactionController::class, 'destroy'])->name('destroy');
        Route::post('/{transaction}/restore', [TransactionController::class, 'restore'])->name('restore');
    });

    Route::prefix('fee-concessions')->name('fee-concessions.')->middleware(['auth'])->group(function () {
        Route::resource('/', FeeConcessionController::class)->parameters(['' => 'feeConcession']);
        Route::delete('/', [FeeConcessionController::class, 'destroy'])->name('destroy');
        Route::post('/{feeConcession}/restore', [FeeConcessionController::class, 'restore'])->name('restore');
    });

    Route::prefix('fee-installments')->name('fee-installments.')->middleware(['auth'])->group(function () {
        Route::resource('/', FeeInstallmentController::class)->parameters(['' => 'feeInstallment']);
        Route::delete('/', [FeeInstallmentController::class, 'destroy'])->name('destroy');
        Route::post('/{feeInstallment}/restore', [FeeInstallmentController::class, 'restore'])->name('restore');
    });

    Route::prefix('fee-installment-details')->name('fee-installment-details.')->middleware(['auth'])->group(function () {
        Route::resource('/', FeeInstallmentDetailController::class)->parameters(['' => 'feeInstallmentDetail']);
        Route::delete('/', [FeeInstallmentDetailController::class, 'destroy'])->name('destroy');
        Route::post('/{feeInstallmentDetail}/restore', [FeeInstallmentDetailController::class, 'restore'])->name('restore');
    });

    Route::prefix('payments')->name('payments.')->middleware(['auth'])->group(function () {
        Route::resource('/', PaymentController::class)->parameters(['' => 'payment']);
        Route::delete('/', [PaymentController::class, 'destroy'])->name('destroy');
        Route::post('/{payment}/restore', [PaymentController::class, 'restore'])->name('restore');
    });

    Route::prefix('reports')->name('finance-reports.')->middleware(['auth'])->group(function () {
        Route::get('/', [FinancialReportController::class, 'index'])->name('index');
        Route::get('/export', [FinancialReportController::class, 'export'])->name('export');
    });
});

// Miscellenous
Route::get('/configs', [ConfigController::class, 'index'])->name('configs.index');
Route::post('/configs', [ConfigController::class, 'store'])->name('configs.store');
Route::get('/configs/{config}', [ConfigController::class, 'show'])->name('configs.show');
Route::put('/configs/{config}', [ConfigController::class, 'update'])->name('configs.update');
Route::delete('/configs', [ConfigController::class, 'destroy'])->name('configs.destroy');
Route::post('/configs/restore', [ConfigController::class, 'restore'])->name('configs.restore');


Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::post('/events', [EventController::class, 'store'])->name('events.store');
Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
Route::delete('/events', [EventController::class, 'destroy'])->name('events.destroy');
Route::post('/events/restore', [EventController::class, 'restore'])->name('events.restore');

Route::get('/event-types', [EventTypeController::class, 'index'])->name('event-types.index');
Route::post('/event-types', [EventTypeController::class, 'store'])->name('event-types.store');
Route::get('/event-types/{eventType}', [EventTypeController::class, 'show'])->name('event-types.show');
Route::put('/event-types/{eventType}', [EventTypeController::class, 'update'])->name('event-types.update');
Route::delete('/event-types', [EventTypeController::class, 'destroy'])->name('event-types.destroy');
Route::post('/event-types/restore', [EventTypeController::class, 'restore'])->name('event-types.restore');

Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');
Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
Route::get('/feedback/{feedback}', [FeedbackController::class, 'show'])->name('feedback.show');
Route::put('/feedback/{feedback}', [FeedbackController::class, 'update'])->name('feedback.update');
Route::delete('/feedback', [FeedbackController::class, 'destroy'])->name('feedback.destroy');
Route::post('/feedback/restore', [FeedbackController::class, 'restore'])->name('feedback.restore');

Route::get('/notices', [NoticeController::class, 'index'])->name('notices.index');
Route::post('/notices', [NoticeController::class, 'store'])->name('notices.store');
Route::get('/notices/{notice}', [NoticeController::class, 'show'])->name('notices.show');
Route::put('/notices/{notice}', [NoticeController::class, 'update'])->name('notices.update');
Route::delete('/notices', [NoticeController::class, 'destroy'])->name('notices.destroy');
Route::post('/notices/restore', [NoticeController::class, 'restore'])->name('notices.restore');
Route::delete('/notices/force-delete', [NoticeController::class, 'forceDestroy'])->name('notices.force-destroy');
Route::post('/notices/{notice}/mark-read', [NoticeController::class, 'markRead'])->name('notices.mark-read');

// Transportation
Route::get('/routes', [RouteController::class, 'index'])->name('routes.index');
Route::post('/routes', [RouteController::class, 'store'])->name('routes.store');
Route::get('/routes/{route}', [RouteController::class, 'show'])->name('routes.show');
Route::put('/routes/{route}', [RouteController::class, 'update'])->name('routes.update');
Route::delete('/routes', [RouteController::class, 'destroy'])->name('routes.destroy');
Route::post('/routes/restore', [RouteController::class, 'restore'])->name('routes.restore');

Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show'])->name('vehicles.show');
Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
Route::delete('/vehicles', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
Route::post('/vehicles/restore', [VehicleController::class, 'restore'])->name('vehicles.restore');
Route::post('/vehicles/{vehicle}/assign-driver', [VehicleController::class, 'assignDriver'])->name('vehicles.assign-driver');

Route::get('/vehicles/{vehicle}/documents', [VehicleDocumentController::class, 'index'])->name('vehicle-documents.index');
Route::post('/vehicles/{vehicle}/documents', [VehicleDocumentController::class, 'store'])->name('vehicle-documents.store');
Route::get('/vehicles/{vehicle}/documents/{vehicleDocument}', [VehicleDocumentController::class, 'show'])->name('vehicle-documents.show');
Route::put('/vehicles/{vehicle}/documents/{vehicleDocument}', [VehicleDocumentController::class, 'update'])->name('vehicle-documents.update');
Route::delete('/vehicles/{vehicle}/documents', [VehicleDocumentController::class, 'destroy'])->name('vehicle-documents.destroy');
Route::post('/vehicles/{vehicle}/documents/restore', [VehicleDocumentController::class, 'restore'])->name('vehicle-documents.restore');
Route::delete('/vehicles/{vehicle}/documents/force-delete', [VehicleDocumentController::class, 'forceDestroy'])->name('vehicle-documents.force-destroy');

// Vehicle expense routes
Route::get('/vehicles/{vehicle}/expenses', [VehicleExpenseController::class, 'index'])->name('vehicle-expenses.index');
Route::post('/vehicles/{vehicle}/expenses', [VehicleExpenseController::class, 'store'])->name('vehicle-expenses.store');
Route::get('/vehicles/{vehicle}/expenses/{vehicleExpense}', [VehicleExpenseController::class, 'show'])->name('vehicle-expenses.show');
Route::put('/vehicles/{vehicle}/expenses/{vehicleExpense}', [VehicleExpenseController::class, 'update'])->name('vehicle-expenses.update');
Route::delete('/vehicles/{vehicle}/expenses', [VehicleExpenseController::class, 'destroy'])->name('vehicle-expenses.destroy');
Route::post('/vehicles/{vehicle}/expenses/restore', [VehicleExpenseController::class, 'restore'])->name('vehicle-expenses.restore');

// Hostel routes
Route::get('/hostels', [HostelController::class, 'index'])->name('hostels.index');
Route::post('/hostels', [HostelController::class, 'store'])->name('hostels.store');
Route::get('/hostels/{hostel}', [HostelController::class, 'show'])->name('hostels.show');
Route::put('/hostels/{hostel}', [HostelController::class, 'update'])->name('hostels.update');
Route::delete('/hostels', [HostelController::class, 'destroy'])->name('hostels.destroy');

// Hostel room routes
Route::get('/hostels/{hostel}/rooms', [HostelRoomController::class, 'index'])->name('hostel-rooms.index');
Route::post('/hostels/{hostel}/rooms', [HostelRoomController::class, 'store'])->name('hostel-rooms.store');
Route::get('/hostels/{hostel}/rooms/{hostelRoom}', [HostelRoomController::class, 'show'])->name('hostel-rooms.show');
Route::put('/hostels/{hostel}/rooms/{hostelRoom}', [HostelRoomController::class, 'update'])->name('hostel-rooms.update');
Route::delete('/hostels/{hostel}/rooms', [HostelRoomController::class, 'destroy'])->name('hostel-rooms.destroy');

// Hostel assignment routes
Route::get('/hostels/{hostel}/rooms/{hostelRoom}/assignments', [HostelAssignmentController::class, 'index'])->name('hostel-assignments.index');
Route::post('/hostels/{hostel}/rooms/{hostelRoom}/assignments', [HostelAssignmentController::class, 'store'])->name('hostel-assignments.store');
Route::get('/hostels/{hostel}/rooms/{hostelRoom}/assignments/{hostelAssignment}', [HostelAssignmentController::class, 'show'])->name('hostel-assignments.show');
Route::put('/hostels/{hostel}/rooms/{hostelRoom}/assignments/{hostelAssignment}', [HostelAssignmentController::class, 'update'])->name('hostel-assignments.update');
Route::delete('/hostels/{hostel}/rooms/{hostelRoom}/assignments', [HostelAssignmentController::class, 'destroy'])->name('hostel-assignments.destroy');

// routes/web.php
Route::get('/notifications/history', [NotificationLogController::class, 'index'])
    ->name('notifications.history');

require __DIR__ . '/auth.php';
require __DIR__ . '/options.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/employee_management.php';
