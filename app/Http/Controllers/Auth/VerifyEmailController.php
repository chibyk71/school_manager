<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\UserManagement\UserAccountService;
use Ichtrojan\Otp\Otp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Verify ownership of the Person primary email for the authenticated account.
 * Sets auth_email_verified_at only; does not enable email login.
 *
 * Invalid OTP guesses are rate-limited independently of resend throttling.
 * Threshold: 5 failures per account; 15-minute decay. Blocked requests do not
 * increment the counter. Success clears the limiter. Verification sets
 * auth_email_verified_at only — it does not enable email as a login identifier.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, UserAccountService $accounts): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            throw ValidationException::withMessages(['otp' => 'Unauthenticated.']);
        }

        $email = $user->profile?->email;
        if (! filled($email)) {
            throw ValidationException::withMessages(['otp' => 'No primary email on the linked person.']);
        }

        if ($user->auth_email_verified_at !== null) {
            return $this->ok($request, 'Email already verified.');
        }

        $throttleKey = 'email-verify-otp:'.$user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'otp' => "Too many verification attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
        $authSettings = function_exists('getMergedSettings')
            ? getMergedSettings('authentication', $school)
            : [];

        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:'.($authSettings['otp_length'] ?? 6)],
        ]);

        $otpInstance = app(Otp::class);
        $response = $otpInstance->validate(strtolower($email), $validated['otp']);
        if (! $response->status) {
            RateLimiter::hit($throttleKey, 60 * 15); // 15 minute decay lock window
            throw ValidationException::withMessages(['otp' => 'Invalid or expired OTP.']);
        }

        RateLimiter::clear($throttleKey);
        $accounts->markAuthEmailVerified($user);

        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties(['email' => $email])
            ->log('Authentication email verified');

        return $this->ok($request, 'Email verified successfully.');
    }

    protected function ok(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->intended(route('dashboard', absolute: false))->with('status', $message);
    }
}
