<?php

namespace App\Http\Controllers\Settings\Financial;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * FeesSettingsController v1.0 – Production-Ready Fees Configuration Management
 *
 * Purpose:
 * Central configuration for school-wide fee policies and payment behavior:
 * - Offline payment instructions
 * - Student panel lock on default
 * - Receipt printing options
 * - Late payment penalties (percentage/fixed, grace period, per day or once)
 *
 * Why this page is valuable:
 * - Controls critical parent/student experience around fees
 * - Handles common school policies (lock access, late fees, receipt format)
 * - Complements Payment Gateways, Tax Rates, and Bank Accounts
 * - Industry standard: most school systems have a "Fees Settings" page
 *
 * Features / Problems Solved:
 * - Uses getMergedSettings() + SaveOrUpdateSchoolSettings() → global defaults + school overrides
 * - No abort() → system admin can set platform defaults
 * - Full validation with nested late payment rules
 * - Clean grouped form with toggles and conditional fields
 * - Responsive PrimeVue layout
 * - Production-ready: security, error handling, structured logs
 *
 * Settings Key: 'financial.fees'
 *
 * Structure:
 *   'financial.fees' => [
 *       'allow_offline_payments' => true,
 *       'offline_payment_instructions' => 'Transfer to any of our bank accounts...',
 *       'lock_student_panel_on_default' => true,
 *       'print_receipt_after_payment' => true,
 *       'receipt_single_page' => false,
 *       'late_payment_penalty' => [
 *           'enabled' => true,
 *           'type' => 'percentage', // or 'fixed'
 *           'amount' => 5.0,
 *           'apply_per' => 'day', // or 'once'
 *           'grace_period_days' => 7,
 *       ],
 *   ]
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.financial.fees
 * - Navigation: Financial → Fees Settings
 * - Frontend: resources/js/Pages/Settings/Financial/Fees.vue
 */

class FeesSettingsController extends Controller
{
    public function index(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $settings = getMergedSettings('financial.fees', $school);

        return Inertia::render('Settings/Financial/Fees', [
            'settings' => $settings,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Financial'],
                ['label' => 'Fees Settings'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'allow_offline_payments' => 'required|boolean',
            'offline_payment_instructions' => 'nullable|string|max:2000',
            'lock_student_panel_on_default' => 'required|boolean',
            'print_receipt_after_payment' => 'required|boolean',
            'receipt_single_page' => 'required|boolean',

            // Late Payment Penalty
            'late_payment_penalty.enabled' => 'required|boolean',
            'late_payment_penalty.type' => 'required_if:late_payment_penalty.enabled,true|in:percentage,fixed',
            'late_payment_penalty.amount' => 'required_if:late_payment_penalty.enabled,true|numeric|min:0|max:999999',
            'late_payment_penalty.apply_per' => 'required_if:late_payment_penalty.enabled,true|in:day,once',
            'late_payment_penalty.grace_period_days' => 'required_if:late_payment_penalty.enabled,true|integer|min:0|max:365',
        ]);

        try {
            SaveOrUpdateSchoolSettings('financial.fees', $validated, $school);

            return redirect()
                ->route('settings.financial.fees')
                ->with('success', 'Fees settings updated successfully.');
        } catch (\Exception $e) {
            Log::error('Fees settings save failed', [
                'school_id' => $school?->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save fees settings.')
                ->withInput();
        }
    }
}