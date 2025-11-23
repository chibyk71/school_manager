<?php

namespace App\Http\Controllers\Settings\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing fee-related settings for schools.
 */
class FeesController extends Controller
{
    /**
     * Display the fees settings page.
     *
     * @param Request $request
     * @return \Inertia\Response
     *
     * @throws \Exception If settings retrieval fails.
     */
    public function index()
    {
        try {
            // Fetch merged fees settings (school-specific or tenant defaults)
            $settings = getMergedSettings('fees', GetSchoolModel());
            return Inertia::render('Settings/Financial/Fees', compact('settings'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch fees settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load fees settings.');
        }
    }

    /**
     * Store or update fees settings.
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
                'allow_offline_payment' => 'required|boolean',
                'offline_bank_payment_instruction' => 'nullable|string',
                'lock_student_panel' => 'required|boolean',
                'print_fees_receipt_for' => 'required|array',
                'single_page' => 'required|boolean',
                'late_payment_punishment' => 'required|array',
                'late_payment_punishment.enabled' => 'required|boolean',
                'late_payment_punishment.type' => 'required|in:percentage,fixed',
                'late_payment_punishment.amount' => 'required|numeric|min:0',
                'late_payment_punishment.apply_per' => 'required|in:day,once',
                'late_payment_punishment.grace_period_days' => 'required|integer|min:0',
            ]);

            // Save or update school-specific fees settings
            SaveOrUpdateSchoolSettings('fees', $validatedData);

            return redirect()
                ->route('settings.finance.fees.index') // Standardized route name
                ->with('success', 'Fees settings updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save fees settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save fees settings.');
        }
    }
}
