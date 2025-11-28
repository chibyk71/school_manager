<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AuthenticationSettingsRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for managing authentication settings in a multi-tenant school system.
 *
 * @group Settings
 */
class AuthenticationController extends Controller
{
    /**
     * Display the authentication settings.
     *
     * Retrieves authentication settings for the active school and renders the view.
     *
     * @param Request $request The HTTP request instance.
     * @return InertiaResponse|JsonResponse
     *
     * @throws \Exception If no active school is found.
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        try {
            // Check permission
            permitted('manage-settings', $request->expectsJson());

            // Get active school
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Retrieve settings
            $settings = getMergedSettings('authentication', $school);

            if ($request->expectsJson()) {
                return response()->json(['settings' => $settings], 200);
            }

            return Inertia::render('Settings/School/Authentication', [
                'settings' => $settings,
                'school_id' => $school->id,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to fetch authentication settings for school: {$e->getMessage()}");
            return $this->respondWithError($request, 'Failed to load authentication settings.');
        }
    }

    /**
     * Store or update authentication settings.
     *
     * Validates and saves authentication settings for the active school.
     *
     * @param AuthenticationSettingsRequest $request The validated HTTP request.
     * @return RedirectResponse|JsonResponse
     *
     * @throws \Exception If settings storage fails or no active school is found.
     */
    public function store(AuthenticationSettingsRequest $request): RedirectResponse|JsonResponse
    {
        try {
            // Check permission
            permitted('manage-settings', $request->expectsJson());

            // Get active school
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Get validated data
            $validated = $request->validated();

            // Save settings
            SaveOrUpdateSchoolSettings('authentication', $validated, $school);

            // Log activity
            activity()
                ->performedOn($school)
                ->causedBy($request->user())
                ->withProperties(['settings' => $validated])
                ->log('Authentication settings updated');

            return $this->respondWithSuccess(
                $request,
                'Authentication settings saved successfully.',
                'settings.authentication.index'
            );
        } catch (\Exception $e) {
            Log::error("Failed to save authentication settings for school: {$e->getMessage()}");
            return $this->respondWithError($request, 'Failed to save authentication settings.');
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

        $response = redirect()->back()->with('success', $message);
        if ($redirectRoute) {
            $response = redirect()->route($redirectRoute);
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

        return redirect()->back()->with('error', $message);
    }
}
