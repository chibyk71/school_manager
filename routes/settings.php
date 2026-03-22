<?php

use App\Http\Controllers\ClassLevelController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\Settings\Academic\AcademicSessionController;
use App\Http\Controllers\Settings\Academic\AcademicSessionSettingsController;
use App\Http\Controllers\Settings\Academic\AttendanceRulesController;
use App\Http\Controllers\Settings\Academic\GradingScalesController;
use App\Http\Controllers\Settings\Academic\SessionActivationController;
use App\Http\Controllers\Settings\Academic\TermClosureController;
use App\Http\Controllers\Settings\Academic\TermController;
use App\Http\Controllers\Settings\Advanced\BackupRestoreController;
use App\Http\Controllers\Settings\Advanced\IpBanController;
use App\Http\Controllers\Settings\Advanced\MaintenanceSettingsController;
use App\Http\Controllers\Settings\Advanced\StorageSettingsController;
use App\Http\Controllers\Settings\Communication\EmailSettingsController;
use App\Http\Controllers\Settings\Communication\EmailTemplatesController;
use App\Http\Controllers\Settings\Communication\OtpSettingsController;
use App\Http\Controllers\Settings\Communication\SmsGatewaysController;
use App\Http\Controllers\Settings\Financial\BankAccountsController;
use App\Http\Controllers\Settings\Financial\FeesSettingsController;
use App\Http\Controllers\Settings\Financial\PaymentGatewaysController;
use App\Http\Controllers\Settings\Financial\TaxRatesController;
use App\Http\Controllers\Settings\General\ApiKeysController;
use App\Http\Controllers\Settings\General\ConnectedAppsController;
use App\Http\Controllers\Settings\General\NotificationsSettingsController;
use App\Http\Controllers\Settings\General\SecuritySettingsController;
use App\Http\Controllers\Settings\SchoolSectionController;
use App\Http\Controllers\Settings\System\CustomFieldsController;
use App\Http\Controllers\Settings\System\GdprSettingsController;
use App\Http\Controllers\Settings\System\InvoiceSettingsController;
use App\Http\Controllers\Settings\System\PrinterSettingsController;
use App\Http\Controllers\Settings\System\UserManagementController;
use App\Http\Controllers\Settings\Website\CompanySettingsController;
use App\Http\Controllers\Settings\Website\LocalizationController;
use App\Http\Controllers\Settings\Website\PrefixesSettingsController;
use App\Http\Controllers\Settings\Website\SocialAuthSettingsController;
use App\Http\Controllers\Settings\Website\ThemesSettingsController;
use App\Http\Controllers\Settings\Website\WebTranslationsController;
use App\Http\Controllers\Settings\School\RolesController;
use App\Http\Controllers\SubjectController;
use Illuminate\Support\Facades\Route;

// ===================================================================
// Admin → Roles & Permissions (existing – untouched)
// ===================================================================
Route::prefix('admin')->middleware(['auth'])->group(function () {
    Route::get('roles', [RolesController::class, 'index'])->name('admin.roles.index');
    Route::post('roles', [RolesController::class, 'store'])->name('admin.roles.store');
    Route::put('roles/{role}', [RolesController::class, 'update'])->name('admin.roles.update');
    Route::delete('roles', [RolesController::class, 'destroy'])->name('admin.roles.destroy');

    Route::get('roles/{role}/permissions', [RolesController::class, 'managePermissions'])
        ->name('admin.roles.permissions.manage');
    Route::put('roles/{role}/permissions', [RolesController::class, 'updatePermissions'])
        ->name('admin.roles.permissions.update');
    Route::post('roles/{role}/permissions/merge', [RolesController::class, 'mergePermissionsFrom'])
        ->name('admin.roles.permissions.merge');

    Route::get('roles/search', [RolesController::class, 'search'])->name('admin.roles.search');
});

// ===================================================================
// Settings → Website & Branding
// ===================================================================
Route::prefix('settings/website')->name('settings.website.')->group(function () {
    Route::get('company', [CompanySettingsController::class, 'index'])->name('company');
    Route::post('company', [CompanySettingsController::class, 'store'])->name('company.store');

    Route::get('localization', [LocalizationController::class, 'index'])->name('localization');
    Route::post('localization', [LocalizationController::class, 'store'])->name('localization.store');

    Route::get('themes', [ThemesSettingsController::class, 'index'])->name('themes');
    Route::post('themes', [ThemesSettingsController::class, 'store'])->name('themes.store');

    Route::get('prefixes', [PrefixesSettingsController::class, 'index'])->name('prefixes');
    Route::post('prefixes', [PrefixesSettingsController::class, 'store'])->name('prefixes.store');

    Route::get('social', [SocialAuthSettingsController::class, 'index'])->name('social');
    Route::post('social', [SocialAuthSettingsController::class, 'store'])->name('social.store');

    Route::get('language', [WebTranslationsController::class, 'index'])->name('language');
    Route::post('language', [WebTranslationsController::class, 'store'])->name('language.store');
});

// ===================================================================
// Settings → General
// ===================================================================
Route::prefix('settings/general')->name('settings.general.')->group(function () {
    Route::get('profile', function (string $school) {
        $school = GetSchoolModel()?->id ?? $school;

        if (!$school) {
            abort(404);
        }
        redirect()->route('schools.edit', ['school' => $school]);
    })->name('profile');

    Route::get('security', [SecuritySettingsController::class, 'index'])->name('security');
    Route::post('security', [SecuritySettingsController::class, 'store'])->name('security.store');

    Route::get('notifications', [NotificationsSettingsController::class, 'index'])->name('notifications');
    Route::post('notifications', [NotificationsSettingsController::class, 'store'])->name('notifications.store');

    Route::get('connected-apps', [ConnectedAppsController::class, 'index'])->name('connected_apps');
    Route::post('connected-apps', [ConnectedAppsController::class, 'store'])->name('connected_apps.store');

    Route::get('api-keys', [ApiKeysController::class, 'index'])->name('api_keys');
    Route::post('api-keys', [ApiKeysController::class, 'store'])->name('api_keys.store');
    Route::post('api-keys/revoke', [ApiKeysController::class, 'destroy'])->name('api_keys.destroy');
});

// ===================================================================
// Settings → Financial
// ===================================================================
Route::prefix('settings/financial')->name('settings.financial.')->group(function () {
    Route::get('gateways', [PaymentGatewaysController::class, 'index'])->name('gateways');
    Route::post('gateways', [PaymentGatewaysController::class, 'store'])->name('gateways.store');

    Route::get('taxes', [TaxRatesController::class, 'index'])->name('taxes');
    Route::post('taxes', [TaxRatesController::class, 'store'])->name('taxes.store');
    Route::put('taxes/{id}', [TaxRatesController::class, 'update'])->name('taxes.update');
    Route::post('taxes/delete', [TaxRatesController::class, 'destroy'])->name('taxes.destroy');

    Route::get('banks', [BankAccountsController::class, 'index'])->name('banks');
    Route::post('banks', [BankAccountsController::class, 'store'])->name('banks.store');
    Route::put('banks/{id}', [BankAccountsController::class, 'update'])->name('banks.update');
    Route::post('banks/delete', [BankAccountsController::class, 'destroy'])->name('banks.destroy');

    Route::get('fees', [FeesSettingsController::class, 'index'])->name('fees');
    Route::post('fees', [FeesSettingsController::class, 'store'])->name('fees.store');
});

// ===================================================================
// Settings → Academic
// ===================================================================
Route::prefix('settings/academic')->name('settings.academic.')->group(function () {
    Route::get('/session/rules', [AcademicSessionSettingsController::class, 'index'])->name('session.rules');
    Route::post('/session/rules', [AcademicSessionSettingsController::class, 'store'])->name('session.rules');

    Route::prefix('academic-sessions')->name('academic-sessions.')->group(function () {
        // Main listing & management
        Route::get('/', [AcademicSessionController::class, 'index'])->name('index');

        // CRUD operations
        Route::post('/', [AcademicSessionController::class, 'store'])->name('store');
        Route::get('/{academicSession}', [AcademicSessionController::class, 'show'])->name('show');
        Route::post('/{academicSession}', [AcademicSessionController::class, 'update'])->name('update');
        Route::delete('/', [AcademicSessionController::class, 'destroy'])->name('destroy'); // Bulk delete

        // Quick state actions (single active session enforcement)
        Route::patch('/{academicSession}/current', [AcademicSessionController::class, 'setCurrent'])->name('set-current');

        // Specialized lifecycle actions (activation & closure)
        Route::patch('/{academicSession}/activate', [SessionActivationController::class, 'activate'])->name('activate');
        Route::patch('/{academicSession}/close', [SessionActivationController::class, 'close'])->name('close');
    });

    // ────────────────────────────────────────────────────────────────
    // Terms (CRUD + Quick Actions)
    // ────────────────────────────────────────────────────────────────
    Route::prefix('terms')->name('terms.')->group(function () {
        // Main listing (can filter by session via query param ?academicSession=id)
        Route::get('/', [TermController::class, 'index'])->name('index');

        // CRUD operations
        Route::post('/', [TermController::class, 'store'])->name('store');
        Route::get('/{term}', [TermController::class, 'show'])->name('show');
        Route::patch('/{term}', [TermController::class, 'update'])->name('update');
        Route::delete('/', [TermController::class, 'destroy'])->name('destroy'); // Bulk delete

        // Quick state action (set active in its session)
        Route::patch('/{term}/active', [TermController::class, 'setActive'])->name('set-active');

        // Restore soft-deleted term
        Route::post('/{id}/restore', [TermController::class, 'restore'])->name('restore');

        // Sensitive lifecycle actions (close & reopen)
        Route::patch('/{term}/close', [TermClosureController::class, 'close'])->name('close');
        Route::patch('/{term}/reopen', [TermClosureController::class, 'reopen'])->name('reopen');
    });

    Route::get('attendance', [AttendanceRulesController::class, 'index'])->name('attendance');
    Route::post('attendance', [AttendanceRulesController::class, 'store'])->name('attendance.store');

    Route::resource('subjects', SubjectController::class);
    Route::post('subjects/delete', [SubjectController::class, 'destroy'])->name('subjects.destroy');
    Route::post('subjects/restore/{id}', [SubjectController::class, 'restore'])->name('subjects.restore');

    // ─── Main grades resource routes ──────────────────────────────────────────────
    Route::resource('grades', GradeController::class)->names('grades');

    // ─── Custom actions ─────────────────────────────────────────────────────────────
    // Bulk delete (soft or force) – used from DataTable bulk actions
    Route::post('grades/destroy', [GradeController::class, 'destroy'])
        ->name('grades.destroy.bulk');

    // Restore soft-deleted grade – used from trashed view or modal
    Route::post('grades/{id}/restore', [GradeController::class, 'restore'])
        ->name('grades.restore')
        ->whereNumber('id');

     Route::get('class-levels', [ClassLevelController::class, 'globalIndex'])
        ->name('class-levels.index');
});

// ===================================================================
// Settings → App & Customization
// ===================================================================
Route::prefix('settings/system')->name('settings.system.')->group(function () {
    Route::get('invoice', [InvoiceSettingsController::class, 'index'])->name('invoice');
    Route::post('invoice', [InvoiceSettingsController::class, 'store'])->name('invoice.store');

    Route::get('gdpr', [GdprSettingsController::class, 'index'])->name('gdpr');
    Route::post('gdpr', [GdprSettingsController::class, 'store'])->name('gdpr.store');

    Route::prefix('custom-fields')->name('custom-fields.')->group(function () {

        // Full resourceful routes (index, create, store, show, edit, update, destroy)
        Route::resource('/', CustomFieldsController::class)->parameters(['' => 'customField']);
        Route::delete('/', [CustomFieldsController::class, 'destroy'])->name('destroy');

        // Extra / custom actions (outside standard REST)
        Route::patch('order', [CustomFieldsController::class, 'reorder'])->name('reorder');

        // Bulk / trash actions
        Route::post('restore', [CustomFieldsController::class, 'restore'])->name('restore');
        Route::delete('force', [CustomFieldsController::class, 'forceDestroy'])->name('force-destroy');

        // Export schema (API-style)
        Route::get('export', [CustomFieldsController::class, 'exportSchema'])->name('export-schema');
    });

    Route::get('printer', [PrinterSettingsController::class, 'index'])->name('printer');
    Route::post('printer', [PrinterSettingsController::class, 'store'])->name('printer.store');

    Route::get('user-management', [UserManagementController::class, 'index'])->name('user-management');
    Route::post('user-management', [UserManagementController::class, 'store'])->name('user-management.store');

    // ── School Sections ───────────────────────────────────────────
    Route::prefix('sections')->name('sections.')->group(function () {

        // Utility endpoints — must come BEFORE {schoolSection} wildcard
        // to prevent "templates" and "options" being swallowed as IDs
        Route::get('templates', [SchoolSectionController::class, 'templates'])
            ->name('templates');

        Route::get('options', [SchoolSectionController::class, 'options'])
            ->name('options');

        Route::post('restore', [SchoolSectionController::class, 'restore'])
            ->name('restore');

        Route::delete('force', [SchoolSectionController::class, 'forceDestroy'])
            ->name('force-delete');

        Route::post('toggle', [SchoolSectionController::class, 'bulkToggle'])
            ->name('bulk-toggle');

        Route::post('reorder', [SchoolSectionController::class, 'reorder'])
            ->name('reorder');

        // Standard resource routes
        Route::get('/', [SchoolSectionController::class, 'index'])
            ->name('index');

        Route::post('/', [SchoolSectionController::class, 'store'])
            ->name('store');

        Route::get('{schoolSection}', [SchoolSectionController::class, 'show'])
            ->name('show');

        Route::match(['put', 'patch'], '{schoolSection}', [SchoolSectionController::class, 'update'])
            ->name('update');

        Route::delete('/', [SchoolSectionController::class, 'destroy'])
            ->name('destroy');
    });
});

// ===================================================================
// Settings → System & Communication
// ===================================================================
Route::prefix('settings/communication')->name('settings.communication.')->group(function () {
    Route::get('email', [EmailSettingsController::class, 'index'])->name('email');
    Route::post('email', [EmailSettingsController::class, 'store'])->name('email.store');
    Route::post('email/test', [EmailSettingsController::class, 'test'])->name('email.test');

    Route::get('templates', [EmailTemplatesController::class, 'index'])->name('templates');
    Route::post('templates', [EmailTemplatesController::class, 'store'])->name('templates.store');

    Route::get('sms', [SmsGatewaysController::class, 'index'])->name('sms');
    Route::post('sms', [SmsGatewaysController::class, 'store'])->name('sms.store');

    Route::get('otp', [OtpSettingsController::class, 'index'])->name('otp');
    Route::post('otp', [OtpSettingsController::class, 'store'])->name('otp.store');
    Route::post('otp/test', [OtpSettingsController::class, 'test'])->name('otp.test');
});

// ===================================================================
// Settings → Advanced / Other
// ===================================================================
Route::prefix('settings/advanced')->name('settings.advanced.')->group(function () {
    Route::get('storage', [StorageSettingsController::class, 'index'])->name('storage');
    Route::post('storage', [StorageSettingsController::class, 'store'])->name('storage.store');

    Route::get('backup', [BackupRestoreController::class, 'index'])->name('backup');
    Route::post('backup/create', [BackupRestoreController::class, 'create'])->name('backup.create');
    Route::get('backup/download/{filename}', [BackupRestoreController::class, 'download'])->name('backup.download');
    Route::delete('backup/{filename}', [BackupRestoreController::class, 'destroy'])->name('backup.destroy');

    Route::get('ip', [IpBanController::class, 'index'])->name('ip');
    Route::post('ip', [IpBanController::class, 'store'])->name('ip.store');
    Route::post('ip/delete', [IpBanController::class, 'destroy'])->name('ip.destroy');

    Route::get('maintenance', [MaintenanceSettingsController::class, 'index'])->name('maintenance');
    Route::post('maintenance', [MaintenanceSettingsController::class, 'store'])->name('maintenance.store');
});
