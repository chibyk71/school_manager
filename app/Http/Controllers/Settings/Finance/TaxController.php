<?php

namespace App\Http\Controllers\Settings\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing tax settings for schools.
 */
class TaxController extends Controller
{
    /**
     * Display the tax settings page.
     *
     * @param Request $request
     * @return \Inertia\Response
     *
     * @throws \Exception If settings retrieval fails.
     */
    public function index()
    {
        try {
            // Fetch merged tax settings (school-specific or tenant defaults)
            $settings = getMergedSettings('tax', GetSchoolModel());
            return Inertia::render('Settings/Financial/Tax', compact('settings'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch tax settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load tax settings.');
        }
    }

    /**
     * Store or update tax settings.
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
            $validated = $request->validate([
                'tax_rate' => 'required|numeric|min:0|max:100',
                'tax_type' => 'required|string|in:percentage,fixed',
                'apply_to_fees' => 'required|boolean',
            ]);

            // Save or update school-specific tax settings
            SaveOrUpdateSchoolSettings('tax', $validated);

            return redirect()->route('settings.finance.tax')
                ->with('success', 'Tax settings updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save tax settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save tax settings.');
        }
    }
}
