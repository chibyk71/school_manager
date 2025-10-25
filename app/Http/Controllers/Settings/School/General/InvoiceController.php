<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing invoice settings in a single-tenant school system.
 */
class InvoiceController extends Controller
{
    /**
     * Display the invoice settings.
     *
     * Retrieves invoice settings for the active school and renders the view.
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

            $settings = getMergedSettings('website.invoice', $school);

            return Inertia::render('Settings/School/Invoice', [
                'settings' => $settings,
            ], 'resources/js/Pages/Settings/School/Invoice.vue');
        } catch (\Exception $e) {
            Log::error('Failed to fetch invoice settings: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load invoice settings.');
        }
    }

    /**
     * Store or update invoice settings.
     *
     * Validates and saves invoice settings for the active school.
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
                'invoice_logo' => 'required|string|max:255',
                'invoice_prefix' => 'required|string|max:50',
                'invoice_due' => 'required|string|max:50',
                'invoice_round_off' => 'required|string|max:50',
                'show_company_details' => 'required|boolean',
                'invoice_header_terms' => 'required|string',
                'invoice_footer_terms' => 'required|string',
            ]);

            SaveOrUpdateSchoolSettings('website.invoice', $validated, $school);

            return redirect()
                ->route('settings.invoice.index')
                ->with('success', 'Invoice settings saved successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save invoice settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save invoice settings: ' . $e->getMessage());
        }
    }
}
