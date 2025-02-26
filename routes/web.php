<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings\Profile\GeneralController;
use App\Http\Controllers\Settings\School\General\InvoiceController;
use App\Http\Controllers\Settings\School\General\LocalizationController;
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
});

require __DIR__.'/auth.php';
