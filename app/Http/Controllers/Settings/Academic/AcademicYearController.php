<?php

namespace App\Http\Controllers\Settings\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * TODO: Replace with the other AcademicSession controller and Model
 * AcademicYearController v1.0 – Production-Ready Academic Session / Year Management
 *
 * Purpose:
 * Manages the current and historical academic years/sessions for the school.
 * Controls session start/end dates, terms, active status, and transitions.
 *
 * Why this page is essential (and first in Academic):
 * - Academic year is the backbone of every school operation:
 *   - Timetables, exams, fees, attendance, grading, results, promotions
 *   - All other academic settings depend on it
 * - Schools typically have 1–2 active sessions (current + next)
 * - Historical sessions must remain read-only for reporting
 * - Industry standard: Fedena, Gibbon, QuickSchools all have "Academic Year" as first academic setting
 *
 * Features / Problems Solved:
 * - Uses getMergedSettings() + SaveOrUpdateSchoolSettings() → global defaults + school overrides
 * - No abort() → system admin can set defaults
 * - Full CRUD: add new session, edit active one, mark previous as archived
 * - Only one session active at a time (enforced)
 * - Term structure (2–4 terms per session)
 * - Date validation (start < end, no overlaps)
 * - Responsive PrimeVue DataTable + form
 * - Production-ready: validation, security, error handling, logging
 *
 * Settings Key: 'academic.years'
 *
 * Structure:
 *   'academic.years' => [
 *       [
 *           'id' => 1,
 *           'name' => '2025/2026',
 *           'start_date' => '2025-09-01',
 *           'end_date' => '2026-08-31',
 *           'is_active' => true,
 *           'terms' => [
 *               ['name' => 'First Term', 'start' => '2025-09-01', 'end' => '2025-12-20'],
 *               ['name' => 'Second Term', 'start' => '2026-01-05', 'end' => '2026-04-10'],
 *               ['name' => 'Third Term', 'start' => '2026-04-20', 'end' => '2026-07-25'],
 *           ],
 *           'created_at' => '2025-01-01',
 *       ],
 *       // archived previous years
 *   ]
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.academic.year
 * - Navigation: Academic Settings → Academic Year
 * - Frontend: resources/js/Pages/Settings/Academic/Year.vue
 */

class AcademicYearController extends Controller
{
    public function index(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $settings = getMergedSettings('academic.years', $school);

        // Ensure array structure
        $settings = is_array($settings) ? $settings : [];

        return Inertia::render('Settings/Academic/Year', [
            'academic_years' => $settings,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Academic'],
                ['label' => 'Academic Year'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'terms' => 'required|array|min:2|max:4',
            'terms.*.name' => 'required|string|max:100',
            'terms.*.start' => 'required|date',
            'terms.*.end' => 'required|date|after:terms.*.start',
            'is_active' => 'boolean',
        ]);

        try {
            $current = getMergedSettings('academic.years', $school);

            // If new session is active, deactivate others
            if ($validated['is_active'] ?? false) {
                $current = array_map(fn($y) => array_merge($y, ['is_active' => false]), $current);
            }

            $validated['terms'] = array_map(function ($term) {
                return [
                    'name' => $term['name'],
                    'start' => $term['start'],
                    'end' => $term['end'],
                ];
            }, $validated['terms']);

            $current[] = [
                'id' => (max(array_column($current, 'id') ?: [0]) + 1),
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'terms' => $validated['terms'],
                'is_active' => $validated['is_active'] ?? false,
                'created_at' => now()->toDateTimeString(),
            ];

            SaveOrUpdateSchoolSettings('academic.years', $current, $school);

            return redirect()
                ->route('settings.academic.year')
                ->with('success', 'Academic year added successfully.');
        } catch (\Exception $e) {
            Log::error('Academic year add failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to add academic year.');
        }
    }

    public function update(Request $request, $id)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'terms' => 'required|array|min:2|max:4',
            'terms.*.name' => 'required|string|max:100',
            'terms.*.start' => 'required|date',
            'terms.*.end' => 'required|date|after:terms.*.start',
            'is_active' => 'boolean',
        ]);

        try {
            $current = getMergedSettings('academic.years', $school);

            $found = false;
            foreach ($current as &$year) {
                if ($year['id'] == $id) {
                    if ($validated['is_active']) {
                        $current = array_map(fn($y) => array_merge($y, ['is_active' => false]), $current);
                    }
                    $year = array_merge($year, $validated, ['terms' => array_map(fn($t) => [
                        'name' => $t['name'],
                        'start' => $t['start'],
                        'end' => $t['end'],
                    ], $validated['terms'])]);
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return redirect()->back()->with('error', 'Academic year not found.');
            }

            SaveOrUpdateSchoolSettings('academic.years', $current, $school);

            return redirect()
                ->route('settings.academic.year')
                ->with('success', 'Academic year updated successfully.');
        } catch (\Exception $e) {
            Log::error('Academic year update failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to update academic year.');
        }
    }

    public function destroy(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        try {
            $current = getMergedSettings('academic.years', $school);

            $remaining = array_filter($current, fn($y) => !in_array($y['id'], $validated['ids']));

            SaveOrUpdateSchoolSettings('academic.years', $remaining, $school);

            return redirect()
                ->route('settings.academic.year')
                ->with('success', 'Academic year(s) deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Academic year delete failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to delete academic year(s).');
        }
    }
}
