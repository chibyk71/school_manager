<?php

namespace App\Http\Controllers\Settings\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing user profile settings.
 */
class GeneralController extends Controller
{
    /**
     * Display the user profile settings page.
     *
     * Retrieves merged profile settings (tenant defaults and user-specific overrides)
     * and renders the profile settings view.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Inertia\Response The Inertia response with profile settings.
     *
     * @throws \Exception If settings retrieval fails or user is not authenticated.
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                throw new \Exception('User not authenticated.');
            }

            // Fetch merged profile settings for the user
            $settings = getMergedSettings('profile.general', $user, $request->branch_id);

            return Inertia::render('Settings/Profile/General', compact('settings'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch profile settings: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load profile settings.');
        }
    }

    /**
     * Update user profile settings.
     *
     * Validates and updates the user's profile settings, merging with existing settings
     * and saving to the database.
     *
     * @param Request $request The incoming HTTP request with validated data.
     * @return \Illuminate\Http\RedirectResponse Redirects to the profile settings page.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If settings update fails or user is not authenticated.
     */
    public function update(Request $request)
    {
        try {
            // Check if user has permission to update profile settings
            permitted('manage-profile-settings');

            $user = Auth::user();
            if (!$user) {
                throw new \Exception('User not authenticated.');
            }

            // Validate incoming request data
            $validated = $request->validate([
                'two_factor' => 'sometimes|boolean',
                'is_email_verified' => 'sometimes|boolean',
                'is_phone_verified' => 'sometimes|boolean',
                'email' => 'sometimes|email|max:255',
                'phone' => 'sometimes|string|max:20|regex:/^([0-9\s\-\+\(\)]*)$/',
            ]);

            // Fetch existing settings and merge with validated data
            $settings = getMergedSettings('profile.general', $user, $request->branch_id);
            $settings = array_merge($settings, array_filter($validated, fn($value) => $value !== null));

            // Save updated settings
            SaveOrUpdateSchoolSettings('profile.general', $settings, $user, $request->branch_id);

            return redirect()
                ->route('settings.profile.general.index')
                ->with('success', 'Profile updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update profile settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update profile settings.');
        }
    }
}
