<?php

namespace App\Http\Controllers\Settings\System;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing OTP settings in a single-tenant school system.
 */
class OtpController extends Controller
{
    /**
     * Display the OTP settings.
     *
     * Retrieves OTP settings for the active school and renders the view.
     *
     * @return \Inertia\Response The Inertia response with settings data.
     *
     * @throws \Exception If settings retrieval fails or no active school is found.
     */
    public function index()
    {
        try {
            permitted('manage-settings');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $setting = getMergedSettings('otp', $school);

            return Inertia::render('Settings/School/OTP', [
                'setting' => $setting,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch OTP settings: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load OTP settings.');
        }
    }

    /**
     * Store or update OTP settings.
     *
     * Validates and saves OTP settings for the active school.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If settings storage fails.
     */
    public function store(Request $request)
    {
        try {
            permitted('manage-settings');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $validated = $request->validate([
                'otp_type' => 'required|string|in:email,sms',
                'limit' => 'required|numeric|min:4|max:10',
                'eol' => 'required|numeric|min:1|max:60',
            ]);

            SaveOrUpdateSchoolSettings('otp', $validated, $school);

            return redirect()
                ->route('settings.otp.index')
                ->with('success', 'OTP settings updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save OTP settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save OTP settings: ' . $e->getMessage());
        }
    }
}
