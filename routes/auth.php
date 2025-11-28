<?php

use App\Http\Controllers\Auth\{
    AuthenticatedSessionController,
    ConfirmablePasswordController,
    EmailVerificationNotificationController,
    EmailVerificationPromptController,
    NewPasswordController,
    PasswordController,
    PasswordResetLinkController,
    RegisteredUserController,
    VerifyEmailController          // ← this one controller handles both link & OTP
};
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest Routes (unauthenticated)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {

    // Register
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    // Login
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Forgot Password – Request OTP (email / SMS)
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email'); // kept name for Laravel Fortify compatibility

    // Reset Password – Enter OTP + new password
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // ── Email Verification (OTP version) ─────────────────────────────────
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');                    // /verify-email → VerifyEmail.vue

    // Resend OTP
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Submit OTP (new route – this was missing!)
    Route::post('verify-email', [VerifyEmailController::class, '__invoke'])
        ->name('verification.verify');                    // POST /verify-email → OTP submit

    // Old signed URL fallback (still supported by Laravel)
    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify.link');

    // ── Password Confirmation (for sensitive actions) ─────────────────────
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    // ── Change Password (from profile/settings) ───────────────────────────
    Route::put('password', [PasswordController::class, 'update'])
        ->name('password.update');

    // ── Logout ────────────────────────────────────────────────────────────────
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');                     // POST is safer & matches Inertia default
});
