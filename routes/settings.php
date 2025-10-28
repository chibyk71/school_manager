<?php

use App\Http\Controllers\Settings\Finance\FeesController;
use App\Http\Controllers\Settings\Finance\TaxController;
use App\Http\Controllers\Settings\Others\MaintenanceController;
use App\Http\Controllers\Settings\Others\StorageController;
use App\Http\Controllers\Settings\Profile\GeneralController;
use App\Http\Controllers\Settings\School\Email\EmailController;
use App\Http\Controllers\Settings\School\Email\TemplateController;
use App\Http\Controllers\Settings\School\General\CustomFieldController;
use App\Http\Controllers\Settings\School\InvoiceController;
use App\Http\Controllers\Settings\School\LocalizationController;
use App\Http\Controllers\Settings\Financial\PaymentsController;
use App\Http\Controllers\Settings\School\SMSController;
use App\Http\Controllers\Settings\School\GDPRController;
use App\Http\Controllers\Settings\School\OtpController;
use App\Http\Controllers\Settings\School\PermissionController;
use App\Http\Controllers\Settings\School\RolesController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'settings'], function () {

    // settings
    Route::get('profile/general', [GeneralController::class, 'index'])->name('profile.setting');
    Route::post('profile/general', [GeneralController::class, 'update'])->name('profile.setting.update');

    Route::get('/school/permission', [PermissionController::class, 'index'])->name('settings.permission');
    Route::post('/school/permission', [PermissionController::class, 'store']);

    Route::get('/school/roles', [RolesController::class, 'index'])->name('settings.roles');
    Route::post('/school/roles', [RolesController::class, 'store']);

    Route::get('/website/localization', [LocalizationController::class, 'index'])->name('website.localization');
    Route::post('/website/localization', [LocalizationController::class, 'store'])->name('website.localization.post');

    Route::get('/website/invoice', [InvoiceController::class, 'index'])->name('website.invoice');
    Route::post('/website/invoice', [InvoiceController::class, 'store'])->name('website.invoice.post');

    Route::get('/website/custom_field', [CustomFieldController::class, 'index'])->name('website.custom-field');
    Route::post('/website/custom_field', [CustomFieldController::class, 'store'])->name('website.custom-field.post');

    Route::get('/system/email', [EmailController::class, 'index'])->name('system.email');
    Route::post('/system/email', [EmailController::class, 'store'])->name('system.email.post');

    Route::get('/system/email/templates', [TemplateController::class, 'index'])->name('system.email.template');
    Route::post('/system/email/templates', [TemplateController::class, 'store'])->name('system.email.template.post');

    Route::get('/system/sms', [SMSController::class, 'index'])->name('system.sms');
    Route::post('/system/sms', [SMSController::class, 'store']);

    Route::get('/system/otp', [OtpController::class, 'index'])->name('system.otp');
    Route::post('/system/otp', [OtpController::class, 'store'])->name('system.otp.post');

    Route::get('/system/gdpr_cookies', [GDPRController::class, 'index'])->name('system.gdpr');
    Route::post('/system/gdpr_cookies', [GDPRController::class, 'store'])->name('system.gdpr.post');

    Route::get('/finance/payment', [PaymentsController::class, 'index'])->name('settings.payment-gate-ways');
    Route::post('/finance/payment', [PaymentsController::class, 'store']);

    Route::get('/finance/tax', [TaxController::class, 'index'])->name('settings.tax');
    Route::post('/finance/tax', [TaxController::class, 'store']);

    Route::get('/finance/fees', [FeesController::class, 'index'])->name('settings.fees');
    Route::post('/finance/fees', [FeesController::class, 'store']);

    Route::get('/others/maintainance', [MaintenanceController::class, 'index'])->name('settings.maintainance');
    Route::post('/others/maintainance', [MaintenanceController::class, 'store']);

    Route::get('/others/storage', [StorageController::class, 'index'])->name('settings.storage');
    Route::post('/others/storage', [StorageController::class, 'store']);
})->middleware('auth');
