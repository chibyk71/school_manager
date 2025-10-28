<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SMSSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Inertia\Response;

/**
 * Controller for managing SMS settings in a single-tenant school system.
 */
class SMSController extends Controller
{
    /**
     * Display the SMS settings.
     *
     * Retrieves SMS settings for the active school and renders the view.
     *
     * @return \Inertia\Response The Inertia response with settings data.
     *
     * @throws \Exception If settings retrieval fails or no active school is found.
     */
    public function index(): JsonResponse|RedirectResponse|Response
    {
        try {
            permitted('manage-settings');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $setting = getMergedSettings('sms', $school);

            if (request()->expectsJson()) {
                return response()->json(['setting' => $setting], 200);
            }

            return Inertia::render('Settings/School/SMS', [
                'setting' => $setting,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch SMS settings: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load SMS settings.');
        }
    }

    /**
     * Store or update SMS settings.
     *
     * Validates and saves SMS settings for the active school.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If settings storage fails.
     */
    public function store(SMSSettingsRequest $request): JsonResponse|RedirectResponse
    {
        try {
            permitted('manage-settings');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $validated = $request->validated();

            SaveOrUpdateSchoolSettings('sms', $validated, $school);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'SMS settings updated successfully.'], 200);
            }

            return redirect()
                ->route('system.sms')
                ->with('success', 'SMS settings updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save SMS settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save SMS settings: ' . $e->getMessage());
        }
    }
}
