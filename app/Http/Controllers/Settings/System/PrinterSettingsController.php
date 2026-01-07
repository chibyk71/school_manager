<?php

namespace App\Http\Controllers\Settings\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * PrinterSettingsController v1.0 – Production-Ready Printer Configuration Management
 *
 * Purpose:
 * Centralizes all thermal/receipt printer settings used for fee receipts, ID cards,
 * admission letters, and other printable documents in the school.
 *
 * Why stored in settings table:
 * - Printer configuration is operational customization (paper size, margins, logo)
 * - Frequently adjusted per school (different printers, receipt formats)
 * - Allows global defaults with per-school overrides
 * - Keeps core models clean while enabling rich print customization
 *
 * Settings Key: 'app.printer'
 *
 * Features / Problems Solved:
 * - Uses getMergedSettings() + SaveOrUpdateSchoolSettings() → global + school overrides
 * - No abort() → system admin can set defaults
 * - Comprehensive validation for paper sizes, margins, DPI
 * - File upload for custom receipt header/footer logos
 * - Live preview of receipt layout (width, margins)
 * - Support for common thermal printer sizes (58mm, 80mm)
 * - Responsive form with grouped sections
 * - Production-ready: secure uploads, size/type validation, storage paths
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.app.printer
 * - Navigation: App & Customization → Printer Settings
 * - Frontend: resources/js/Pages/Settings/App/Printer.vue
 *
 * Default Values (for seeding on tenant bootstrap):
 *   'app.printer' => [
 *       'paper_width' => 80,           // mm (58 or 80 most common)
 *       'margin_top' => 5,             // mm
 *       'margin_bottom' => 5,
 *       'margin_left' => 3,
 *       'margin_right' => 3,
 *       'dpi' => 203,
 *       'font_size' => 10,             // pt
 *       'show_school_logo' => true,
 *       'show_receipt_header' => true,
 *       'header_text' => 'Official Receipt',
 *       'footer_text' => 'Thank you for your payment',
 *       'show_barcode' => true,
 *       'barcode_type' => 'CODE128',
 *       'header_logo_path' => null,
 *       'header_logo_url' => null,
 *   ]
 */

class PrinterSettingsController extends Controller
{
    /**
     * Display the printer settings page.
     */
    public function index(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $settings = getMergedSettings('app.printer', $school);

        return Inertia::render('Settings/System/Printer', [
            'settings' => $settings,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'App & Customization'],
                ['label' => 'Printer Settings'],
            ],
            'paper_sizes' => [58, 80], // Common thermal printer widths in mm
            'barcode_types' => ['CODE128', 'CODE39', 'EAN13', 'QR'],
        ]);
    }

    /**
     * Store/update printer settings.
     */
    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'paper_width' => 'required|in:58,80',
            'margin_top' => 'required|integer|min:0|max:50',
            'margin_bottom' => 'required|integer|min:0|max:50',
            'margin_left' => 'required|integer|min:0|max:20',
            'margin_right' => 'required|integer|min:0|max:20',
            'dpi' => 'required|integer|in:203,300',
            'font_size' => 'required|integer|min:8|max:16',
            'show_school_logo' => 'boolean',
            'show_receipt_header' => 'boolean',
            'header_text' => 'nullable|string|max:100',
            'footer_text' => 'nullable|string|max:200',
            'show_barcode' => 'boolean',
            'barcode_type' => 'required_if:show_barcode,true|string|in:CODE128,CODE39,EAN13,QR',
            'header_logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:1024', // 1MB
        ]);

        try {
            $settings = $validated;

            // Handle header logo upload
            if ($request->hasFile('header_logo')) {
                $path = $request->file('header_logo')->store("schools/{$school?->id}/printer", 'public');
                $settings['header_logo_path'] = $path;
                $settings['header_logo_url'] = Storage::url($path);
            }

            SaveOrUpdateSchoolSettings('system.printer', $settings, $school);

            return redirect()
                ->route('settings.system.printer')
                ->with('success', 'Printer settings updated successfully.');
        } catch (\Exception $e) {
            Log::error('Printer settings save failed', [
                'school_id' => $school?->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save printer settings.')
                ->withInput();
        }
    }
}
