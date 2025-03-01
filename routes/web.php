<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings\Finance\TaxController;
use App\Http\Controllers\Settings\Profile\GeneralController;
use App\Http\Controllers\Settings\School\Email\EmailController;
use App\Http\Controllers\Settings\School\Email\TemplateController;
use App\Http\Controllers\Settings\School\General\CustomFieldController;
use App\Http\Controllers\Settings\School\General\FeesController;
use App\Http\Controllers\Settings\School\General\InvoiceController;
use App\Http\Controllers\Settings\School\General\LocalizationController;
use App\Http\Controllers\Settings\School\PaymentsController;
use App\Http\Controllers\Settings\School\SMSController;
use App\Http\Controllers\Settings\System\GDPRController;
use App\Http\Controllers\Settings\System\OtpController;
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

    // settings
    Route::get('settings/profile/general', [GeneralController::class, 'index'])->name('profile.setting');
    Route::post('settings/profile/general', [GeneralController::class, 'update'])->name('profile.setting.update');

    Route::get('/settings/website/localization', [LocalizationController::class, 'index'])->name('website.localization');
    Route::post('/settings/website/localization', [LocalizationController::class, 'store'])->name('website.localization.post');

    Route::get('/settings/website/invoice', [InvoiceController::class, 'index'])->name('website.invoice');
    Route::post('/settings/website/invoice', [InvoiceController::class, 'store'])->name('website.invoice.post');

    Route::get('/settings/website/custom_field', [CustomFieldController::class, 'index'])->name('website.custom_field');
    Route::post('/settings/website/custom_field', [CustomFieldController::class, 'store'])->name('website.custom_field.post');

    Route::get('/settings/system/email', [EmailController::class, 'index'])->name('system.email');
    Route::post('/settings/system/email', [EmailController::class, 'store'])->name('system.email.post');

    Route::get('/settings/system/email/templates', [TemplateController::class, 'index'])->name('system.email.template');
    Route::post('/settings/system/email/templates', [TemplateController::class, 'store'])->name('system.email.template.post');

    Route::get('/settings/system/sms', [SMSController::class, 'index'])->name('system.sms');
    Route::post('/settings/system/sms', [SMSController::class, 'store'])->name('system.sms.post');

    Route::get('/settings/system/otp', [OtpController::class, 'index'])->name('system.otp');
    Route::post('/settings/system/otp', [OtpController::class, 'store'])->name('system.otp.post');

    Route::get('/settings/system/gdpr_cookies', [GDPRController::class, 'index'])->name('system.gdpr');
    Route::post('/settings/system/gdpr_cookies', [GDPRController::class, 'store'])->name('system.gdpr.post');

    Route::get('/settings/finance/payment', [PaymentsController::class, 'index'])->name('settings.payment-gate-ways');
    Route::post('/settings/finance/payment', [PaymentsController::class, 'store']);
    
    Route::get('/settings/finance/tax', [TaxController::class, 'index'])->name('settings.tax');
    Route::post('/settings/finance/tax', [TaxController::class, 'store']);
    
    Route::get('/settings/finance/fees', [FeesController::class, 'index'])->name('settings.fees');
    Route::post('/settings/finance/fees', [FeesController::class, 'store']);

});

require __DIR__.'/auth.php';
