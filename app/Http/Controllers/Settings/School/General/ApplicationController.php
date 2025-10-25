<?php

namespace App\Http\Controllers\Settings\School\General;

use App\Http\Controllers\Controller;
use App\Models\Tenant\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing general application settings for schools.
 */
class ApplicationController extends Controller
{
    /**
     * Display the application settings page.
     *
     * Retrieves merged application settings (tenant defaults, school-specific, or branch-specific overrides)
     * and renders the application settings view.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Inertia\Response The Inertia response with application settings.
     *
     * @throws \Exception If settings retrieval fails or school is not found.
     */
    public function index(Request $request)
    {
        try {
            // Fetch merged application settings (tenant, school, or branch-specific)
            $settings = getMergedSettings('application', GetSchoolModel(), $request->branch_id);

            return Inertia::render('Settings/School/Application', compact('settings'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch application settings: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load application settings.');
        }
    }

    /**
     * Store or update application settings.
     *
     * Validates and saves application configuration settings for the school or branch.
     *
     * @param Request $request The incoming HTTP request with validated data.
     * @return \Illuminate\Http\RedirectResponse Redirects to the application settings page.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If settings update fails or school is not found.
     */
    public function store(Request $request)
    {
        try {
            // Check if user has permission to manage application settings
            permitted('manage-application-settings');

            // Validate incoming request data
            $validatedData = $request->validate([
                'app_name' => 'required|string|max:255',
                'short_name' => 'required|string|max:50',
                'sidebar_default' => 'required|string|in:full,compact,mini',
                'table_pagination' => 'required|integer|min:1|max:100',
                'outside_click' => 'required|boolean',
                'allow_school_custom_logo' => 'required|boolean',
                'allow_school_default_payment' => 'required|boolean',
            ]);

            // Save or update school/branch-specific application settings
            SaveOrUpdateSchoolSettings('application', $validatedData, GetSchoolModel(), $request->branch_id);

            return redirect()
                ->route('settings.school.application.index')
                ->with('success', 'Application settings updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save application settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save application settings.');
        }
    }
}
