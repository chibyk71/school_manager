<?php

namespace App\Http\Controllers\Settings\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * InvoiceSettingsController v1.0 – Production-Ready Invoice Configuration Management
 *
 * Purpose:
 * Centralizes all invoice-related customization for the school:
 * - Numbering format & prefixes
 * - Templates & branding
 * - Due dates, terms, tax settings
 * - Payment instructions & notes
 *
 * Why stored in settings table:
 * - Invoice appearance and rules are branding/operational preferences
 * - Frequently customized per school (different numbering, terms, logos)
 * - Allows global defaults with per-school overrides
 * - Clean separation from core financial logic
 *
 * Settings Key: 'app.invoice'
 *
 * Features / Problems Solved:
 * - Uses getMergedSettings() + SaveOrUpdateSchoolSettings() → global + school overrides
 * - No abort() → system admin can set platform-wide defaults
 * - Comprehensive validation covering numbering, dates, tax, branding
 * - File upload for custom invoice logo (separate from school logo)
 * - Dynamic next invoice number preview
 * - Responsive grouped form matching your PrimeVue/Tailwind stack
 * - Production-ready: secure uploads, size/type validation, storage paths
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.app.invoice
 * - Navigation: App & Customization → Invoice Settings
 * - Frontend: resources/js/Pages/Settings/App/Invoice.vue
 *
 * Default Values (for seeding on tenant bootstrap):
 *   'app.invoice' => [
 *       'prefix' => 'INV',
 *       'next_number' => 1,
 *       'number_digits' => 6,
 *       'template' => 'modern',
 *       'due_days' => 14,
 *       'show_tax' => true,
 *       'tax_rate' => 7.5,
 *       'tax_label' => 'VAT',
 *       'notes' => 'Thank you for your business.',
 *       'terms' => 'Payment due within 14 days.',
 *       'show_logo' => true,
 *       'logo_path' => null,
 *       'logo_url' => null,
 *   ]
 */

class InvoiceSettingsController extends Controller
{
    /**
     * Display the invoice settings page.
     */
    public function index(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel(); // null = global defaults

        $settings = getMergedSettings('app.invoice', $school);

        // Generate next invoice number preview
        $preview = sprintf(
            '%s-%s%0' . ($settings['number_digits'] ?? 6) . 'd',
            $settings['prefix'] ?? 'INV',
            date('Y'),
            $settings['next_number'] ?? 1
        );

        return Inertia::render('Settings/System/Invoice', [
            'settings' => $settings,
            'preview' => $preview,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'App & Customization'],
                ['label' => 'Invoice Settings'],
            ],
        ]);
    }

    /**
     * Store/update invoice settings.
     */
    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'prefix' => 'required|string|max:20',
            'next_number' => 'required|integer|min:1',
            'number_digits' => 'required|integer|min:1|max:10',
            'template' => 'required|string|in:modern,classic,minimal,professional',
            'due_days' => 'required|integer|min:0|max:365',
            'show_tax' => 'boolean',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_label' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'terms' => 'nullable|string|max:1000',
            'show_logo' => 'boolean',
            'invoice_logo' => 'nullable|image|mimes:jpeg,png,jpg,svg,webp|max:2048', // 2MB
        ]);

        try {
            $settings = $validated;

            // Handle logo upload
            if ($request->hasFile('invoice_logo')) {
                $path = $request->file('invoice_logo')->store("schools/{$school?->id}/invoices", 'public');
                $settings['logo_path'] = $path;
                $settings['logo_url'] = Storage::url($path);
            }

            SaveOrUpdateSchoolSettings('app.invoice', $settings, $school);

            return redirect()
                ->route('settings.app.invoice')
                ->with('success', 'Invoice settings updated successfully.');
        } catch (\Exception $e) {
            Log::error('Invoice settings save failed', [
                'school_id' => $school?->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save invoice settings.')
                ->withInput();
        }
    }
}
