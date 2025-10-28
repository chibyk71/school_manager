<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for displaying the email verification prompt in a multi-tenant school system.
 *
 * @group Authentication
 */
class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     *
     * Redirects to the dashboard if email verification is not required or already verified,
     * otherwise renders the verification prompt.
     *
     * @param Request $request The HTTP request instance.
     * @return RedirectResponse|InertiaResponse|JsonResponse
     */
    public function __invoke(Request $request): RedirectResponse|InertiaResponse|JsonResponse
    {
        try {
            // Ensure authenticated user
            $user = $request->user();
            if (!$user) {
                throw new \Exception('Unauthenticated user.');
            }

            // Check school context
            $school = GetSchoolModel();
            if (!$school || $user->school_id !== $school->id) {
                throw new \Exception('Invalid school context.');
            }

            // Check if email verification is required
            $authSettings = getMergedSettings('authentication', $school);
            if (!($authSettings['enable_email_verification'] ?? false)) {
                Log::info("Email verification prompt skipped for user {$user->id} in school {$school->id} as per settings.");
                return $this->respondWithSuccess($request, 'Email verification not required.', 'dashboard');
            }

            // Check if email is already verified
            if ($user->hasVerifiedEmail()) {
                Log::info("Email already verified for user {$user->id}.");
                return $this->respondWithSuccess($request, 'Email already verified.', 'dashboard');
            }

            // Log access to verification prompt
            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties(['school_id' => $school->id])
                ->log('Accessed email verification prompt');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Email verification required.',
                    'school_id' => $school->id,
                    'status' => session('status'),
                ], 200);
            }

            return Inertia::render('Auth/VerifyEmail', [
                'status' => session('status'),
                'school_id' => $school->id,
                'user' => $user->only('id', 'name', 'email'),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to display email verification prompt for user ID {$user?->id}: {$e->getMessage()}");
            return $this->respondWithError($request, 'Failed to load email verification prompt.');
        }
    }

    /**
     * Respond with a success message for web or API requests.
     *
     * @param Request $request The HTTP request instance.
     * @param string $message The success message.
     * @param string|null $redirectRoute Optional redirect route name.
     * @return RedirectResponse|JsonResponse
     */
    protected function respondWithSuccess(Request $request, string $message, ?string $redirectRoute = null): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 200);
        }

        $response = redirect()->back()->with('status', $message);
        if ($redirectRoute) {
            $response = redirect()->intended(route($redirectRoute, absolute: false));
        }

        return $response;
    }

    /**
     * Respond with an error message for web or API requests.
     *
     * @param Request $request The HTTP request instance.
     * @param string $message The error message.
     * @param int $statusCode The HTTP status code.
     * @return RedirectResponse|JsonResponse
     */
    protected function respondWithError(Request $request, string $message, int $statusCode = 400): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], $statusCode);
        }

        return redirect()->back()->withErrors(['email' => $message]);
    }
}