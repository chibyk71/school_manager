<?php

namespace App\Http\Controllers\Settings\School\General;

use App\Http\Controllers\Controller;
use App\Models\Tenant\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Controller for managing branding settings for schools.
 * @deprecated All properties have been moved to School model
 */
class BrandingController extends Controller
{
    /**
     * Display the branding settings page.
     *
     * Retrieves merged branding settings (tenant defaults, school-specific, or branch-specific overrides)
     * and renders the branding settings view.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Inertia\Response The Inertia response with branding settings.
     *
     * @throws \Exception If settings retrieval fails or school is not found.
     */
    public function index(Request $request)
    {
        try {
            // Fetch merged branding settings (tenant, school, or branch-specific)
            $settings = getMergedSettings('branding', GetSchoolModel(), $request->branch_id);

            return Inertia::render('Settings/Branding', compact('settings'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch branding settings: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load branding settings.');
        }
    }

    /**
     * Store or update branding settings.
     *
     * Validates and saves branding configuration settings (logos, colors) for the school or branch.
     * Handles file uploads and deletes old files if replaced.
     *
     * @param Request $request The incoming HTTP request with validated data.
     * @return \Illuminate\Http\RedirectResponse Redirects to the branding settings page.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If settings update or file storage fails.
     */
    public function store(Request $request)
    {
        try {
            // Check if user has permission to manage branding settings
            permitted('manage-branding-settings');

            // Validate incoming request data
            $validated = $request->validate([
                'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'small_logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'favicon' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'primary_color' => 'required|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
                'secondary_color' => 'required|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
                'accent_color' => 'required|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            ]);

            // Fetch existing settings to check for old files
            $existingSettings = getMergedSettings('branding', GetSchoolModel(), $request->branch_id);

            // Handle file uploads and delete old files
            if ($request->hasFile('logo')) {
                if (!empty($existingSettings['logo'])) {
                    Storage::disk('public')->delete($existingSettings['logo']);
                }
                $validated['logo'] = $request->file('logo')->store('branding', 'public');
            }

            if ($request->hasFile('small_logo')) {
                if (!empty($existingSettings['small_logo'])) {
                    Storage::disk('public')->delete($existingSettings['small_logo']);
                }
                $validated['small_logo'] = $request->file('small_logo')->store('branding', 'public');
            }

            if ($request->hasFile('favicon')) {
                if (!empty($existingSettings['favicon'])) {
                    Storage::disk('public')->delete($existingSettings['favicon']);
                }
                $validated['favicon'] = $request->file('favicon')->store('branding', 'public');
            }

            // Save or update school/branch-specific branding settings
            SaveOrUpdateSchoolSettings('branding', $validated, GetSchoolModel(), $request->branch_id);

            return redirect()
                ->route('settings.branding.index')
                ->with('success', 'Branding settings updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save branding settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save branding settings.');
        }
    }
}
