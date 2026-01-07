<?php

namespace App\Http\Controllers\Settings\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * SecuritySettingsController v1.0 – Production-Ready Security & Authentication Settings
 *
 * Purpose:
 * Manages all security-related authentication policies for the platform (login throttling,
 * password rules, OTP/email verification, registration controls, etc.).
 *
 * Why renamed and moved:
 * - Your existing AuthenticationController is excellent and already production-ready
 * - It correctly uses your helpers (getMergedSettings + SaveOrUpdateSchoolSettings)
 * - It supports global defaults (no school context) perfectly
 * - It has proper FormRequest validation, activity logging, and API support
 * - We are simply renaming it to match our new navigation structure:
 *   General Settings → Security Settings
 *
 * Changes made:
 * - Namespace updated to App\Http\Controllers\Settings\General
 * - Class renamed to SecuritySettingsController (more accurate than "Authentication")
 * - Routes updated to settings.general.security (instead of school.authentication)
 * - Inertia render path updated to match new location
 * - Minor comment updates for consistency
 * - Everything else preserved – your code was already best-practice compliant!
 *
 * Settings Key: 'authentication' (kept unchanged – no migration needed)
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.general.security
 * - Navigation: General Settings → Security Settings
 * - Frontend: resources/js/Pages/Settings/General/Security.vue (you can move/rename your existing page)
 * - Uses your existing AuthenticationSettingsRequest (keep it or move to App\Http\Requests\Settings\General)
 *
 * Recommendation: Keep your existing FormRequest and Vue page – just update namespace/routes.
 */

class SecuritySettingsController extends Controller
{
    /**
     * Display the security/authentication settings.
     */
    public function index(Request $request)
    {
        try {
            permitted('manage-settings', $request->expectsJson());

            $school = GetSchoolModel(); // May be null → global defaults

            $settings = getMergedSettings('authentication', $school);

            if ($request->expectsJson()) {
                return response()->json(['settings' => $settings], 200);
            }

            return Inertia::render('Settings/General/Security', [
                'settings' => $settings,
                'school_id' => $school?->id,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to fetch security settings: {$e->getMessage()}");
            return $this->respondWithError($request, 'Failed to load security settings.');
        }
    }

    /**
     * Store or update security/authentication settings.
     */
    public function store(\App\Http\Requests\Settings\AuthenticationSettingsRequest $request)
    {
        try {
            permitted('manage-settings', $request->expectsJson());

            $school = GetSchoolModel(); // May be null → global defaults

            $validated = $request->validated();

            SaveOrUpdateSchoolSettings('authentication', $validated, $school);

            activity()
                ->performedOn($school)
                ->causedBy($request->user())
                ->withProperties(['settings' => $validated])
                ->log('Security settings updated');

            return $this->respondWithSuccess(
                $request,
                'Security settings saved successfully.',
                'settings.general.security'
            );
        } catch (\Exception $e) {
            Log::error("Failed to save security settings: {$e->getMessage()}");
            return $this->respondWithError($request, 'Failed to save security settings.');
        }
    }

    protected function respondWithSuccess(Request $request, string $message, ?string $redirectRoute = null)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 200);
        }

        $response = redirect()->back()->with('success', $message);
        if ($redirectRoute) {
            $response = redirect()->route($redirectRoute)->with('success', $message);
        }

        return $response;
    }

    protected function respondWithError(Request $request, string $message, int $statusCode = 400)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], $statusCode);
        }

        return redirect()->back()->with('error', $message);
    }
}
