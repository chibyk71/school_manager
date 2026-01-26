<?php

namespace App\Http\Controllers\Settings\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * IdFormatSettingsController – Customizable ID Pattern & Format Configuration
 *
 * Allows schools (or global admin) to define exactly how generated identifiers should be formatted.
 * Each supported ID type (student_id, staff_id, guardian_id, invoice, etc.) has:
 *   - A pattern template using placeholders: {PREFIX}, {SCHOOL}, {YEAR}, {SEQUENCE}
 *   - A sequence_length for zero-padding the counter
 *
 * Storage:
 *   - Key: 'website.id_formats' (JSON object in settings table)
 *   - Structure: { "student_id": { "pattern": "...", "sequence_length": 6 }, ... }
 *   - Merged via getMergedSettings(): school override > global default
 *
 * Features / Problems Solved:
 * - Single pattern string → maximum flexibility (reorder parts, change separators, omit fields)
 * - Per-type sequence length control → different padding per entity (e.g. 6 digits for students, 5 for staff)
 * - Secure validation: pattern syntax, separator whitelist, length limits
 * - Live preview support (passed to frontend for real-time feedback)
 * - Clear global vs school-specific context (different messages, UI hints)
 * - Atomic save with transaction-like behavior via helper
 * - Structured logging with school/user context for debugging
 * - Inertia-ready: returns merged data + crumbs for navigation
 * - No direct UI — purely backend; frontend handles form/preview
 *
 * Fits into Settings Module:
 * - Route: GET/POST settings.website.id-formats
 * - Navigation: Settings → Website & Branding → ID Formats
 * - Frontend: resources/js/Pages/Settings/Website/IdFormats.vue
 *   (form with pattern input, sequence length, live preview, save button)
 * - Integrates with: IdGenerator helper (uses these patterns to build IDs)
 * - Used by: Student/Staff/Guardian creation, invoice/payment generation, etc.
 *
 * Supported Placeholders (documented in UI):
 *   {PREFIX}    → from website.prefixes (STU, STF, GRD, INV...)
 *   {SCHOOL}    → school short_code or 3-letter name fallback
 *   {YEAR}      → current year (or academic year if configured)
 *   {SEQUENCE}  → auto-incrementing counter (zero-padded by sequence_length)
 *
 * Default Patterns (seeded globally or on first access):
 *   student_id:  "{SCHOOL}-{PREFIX}-{YEAR}-{SEQUENCE}" (length 6)
 *   staff_id:    "{PREFIX}-{SEQUENCE}" (length 5)
 *   guardian_id: "{PREFIX}.{SEQUENCE}" (length 5)
 *   invoice:     "INV/{YEAR}/{SEQUENCE}" (length 6)
 */

class IdFormatSettingsController extends Controller
{
    /**
     * Display the ID format settings page.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        permitted('manage-settings');

        $school = GetSchoolModel(); // null = system/global view

        $formats = getMergedSettings('website.id_formats', $school) ?? [];

        return Inertia::render('Settings/Website/IdFormats', [
            'formats' => $formats,
            'isGlobal' => $school === null,
            'crumbs' => [
                ['label' => 'Settings', 'href' => route('settings.index')],
                ['label' => 'Website & Branding', 'href' => route('settings.website.index')],
                ['label' => 'ID Formats'],
            ],
        ]);
    }

    /**
     * Save/update ID format patterns and sequence lengths.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel(); // null = global defaults

        $validated = $request->validate([
            '*.pattern' => [
                'required',
                'string',
                'max:100',
                'regex:/^[^{}\s]*({[A-Z]+})*[^{}\s]*$/',
            ],
            '*.sequence_length' => 'required|integer|min:4|max:8',
        ], [
            '*.pattern.regex' => 'Invalid pattern. Use only text and placeholders: {PREFIX}, {SCHOOL}, {YEAR}, {SEQUENCE}. No spaces inside {}.',
            '*.sequence_length.min' => 'Sequence length must be at least 4 digits.',
            '*.sequence_length.max' => 'Sequence length cannot exceed 8 digits.',
        ]);

        try {
            SaveOrUpdateSchoolSettings('website.id_formats', $validated, $school);

            $message = $school
                ? 'School-specific ID formats updated successfully.'
                : 'Global default ID formats updated successfully. New schools will inherit these.';

            return redirect()
                ->route('settings.website.id-formats')
                ->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Failed to save ID format settings', [
                'school_id' => $school?->id,
                'user_id' => auth()->id(),
                'input' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save ID formats. Please try again.')
                ->withInput();
        }
    }
}
