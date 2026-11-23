<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\VerifyEmailNotification;
use Ichtrojan\Otp\Otp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Resend OTP for verifying Person primary email ownership.
 */
class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            throw ValidationException::withMessages(['email' => 'Unauthenticated.']);
        }

        $email = $user->profile?->email;
        if (! filled($email)) {
            throw ValidationException::withMessages(['email' => 'No primary email on the linked person.']);
        }

        if ($user->auth_email_verified_at !== null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Email already verified.'])
                : redirect()->intended(route('dashboard', absolute: false));
        }

        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
        $authSettings = function_exists('getMergedSettings')
            ? getMergedSettings('authentication', $school)
            : [];
        $otpLength = $authSettings['otp_length'] ?? 6;
        $otpValidity = $authSettings['otp_validity'] ?? 10;

        $otpInstance = app(Otp::class);
        $response = $otpInstance->generate(strtolower($email), 'numeric', $otpLength, $otpValidity);
        if (! $response->status) {
            throw ValidationException::withMessages(['email' => 'Failed to generate verification OTP.']);
        }

        Notification::route('mail', $email)->notify(new VerifyEmailNotification($response->otp, $user));

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Verification code sent.']);
        }

        return back()->with('status', 'verification-link-sent');
    }
}
