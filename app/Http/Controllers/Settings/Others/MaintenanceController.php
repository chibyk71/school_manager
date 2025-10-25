<?php

namespace App\Http\Controllers\Settings\Others;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing maintenance settings for schools.
 */
class MaintenanceController extends Controller // Renamed for correctness
{
    /**
     * Display the maintenance settings page.
     *
     * @param Request $request
     * @return \Inertia\Response
     *
     * @throws \Exception If settings retrieval fails.
     */
    public function index()
    {
        try {
            // Fetch merged maintenance settings (school-specific or tenant defaults)
            $settings = getMergedSettings('maintenance', GetSchoolModel());
            return Inertia::render('Settings/Others/Maintenance', compact('settings'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch maintenance settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load maintenance settings.');
        }
    }

    /**
     * Store or update maintenance settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If settings save fails.
     */
    public function store(Request $request)
    {
        try {
            // Validate incoming request data
            $validatedData = $request->validate([
                'maintenance_mode' => 'required|string|in:enabled,disabled',
                'maintenance_key' => 'required|string|max:255',
                'maintenance_mode_url' => 'required|url',
            ]);

            // Save or update school-specific maintenance settings
            SaveOrUpdateSchoolSettings('maintenance', $validatedData);

            return redirect()
                ->route('settings.others.maintenance.index') // Standardized route name
                ->with('success', 'Maintenance settings updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save maintenance settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save maintenance settings.');
        }
    }
}
