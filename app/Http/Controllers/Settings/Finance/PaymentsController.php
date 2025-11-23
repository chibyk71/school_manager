<?php

namespace App\Http\Controllers\Settings\Finance;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing payment settings in a single-tenant school system.
 */
class PaymentsController extends Controller
{
    /**
     * Display the payment settings.
     *
     * Retrieves payment settings for the active school and renders the view.
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

            $settings = getMergedSettings('payment', $school);

            return Inertia::render('Settings/Financial/Payment', [
                'settings' => $settings,
            ], 'resources/js/Pages/Settings/Financial/Payment.vue');
        } catch (\Exception $e) {
            Log::error('Failed to fetch payment settings: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load payment settings.');
        }
    }

    /**
     * Store or update payment settings.
     *
     * Validates and saves payment settings for the active school.
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
                'default_gateway' => 'required|string|in:stripe,paypal,flutterwave,paystack',
                'stripe_key' => 'nullable|string|max:255',
                'stripe_secret' => 'nullable|string|max:255',
                'paypal_client_id' => 'nullable|string|max:255',
                'paypal_secret' => 'nullable|string|max:255',
                'flutterwave_public_key' => 'nullable|string|max:255',
                'flutterwave_secret_key' => 'nullable|string|max:255',
                'paystack_public_key' => 'nullable|string|max:255',
                'paystack_secret_key' => 'nullable|string|max:255',
                'enable_partial_payments' => 'required|boolean',
                'minimum_payment_percentage' => 'nullable|numeric|min:0|max:100',
            ]);

            SaveOrUpdateSchoolSettings('payment', $validated, $school);

            return redirect()
                ->route('settings.payment.index')
                ->with('success', 'Payment settings updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save payment settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save payment settings: ' . $e->getMessage());
        }
    }
}
