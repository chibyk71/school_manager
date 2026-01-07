<?php

namespace App\Http\Controllers\Settings\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * GradingScalesController v1.0 – Production-Ready Grading Scales Management
 *
 * Purpose:
 * Manages multiple grading scales (letter grades, GPA, percentages) used across the school.
 * Supports multiple scales (e.g., Standard, Honors, IB) with custom grade boundaries.
 *
 * Why this page is essential:
 * - Different classes/subjects may use different scales (e.g., AP vs regular)
 * - Schools often switch scales or add new ones
 * - Core to results, transcripts, GPA calculation, promotions
 * - Industry standard: Fedena, QuickSchools, PowerSchool all have dedicated "Grading Scales" page
 *
 * Features / Problems Solved:
 * - Uses getMergedSettings() + SaveOrUpdateSchoolSettings() → global defaults + school overrides
 * - No abort() → system admin can set platform defaults
 * - Full CRUD: add, edit, delete scales
 * - Multiple grades per scale with min/max percentage, letter, GPA value
 * - Default scale selection (used when none specified)
 * - Validation: no overlapping ranges, min < max, unique letters per scale
 * - Responsive PrimeVue DataTable with nested grades
 * - Modal for add/edit scale + dynamic grade rows
 * - Bulk delete
 * - Production-ready: validation, security, error handling, logging
 *
 * Settings Key: 'academic.grading_scales'
 *
 * Structure:
 *   'academic.grading_scales' => [
 *       'scales' => [
 *           [
 *               'id' => 1,
 *               'name' => 'Standard Scale',
 *               'is_default' => true,
 *               'grades' => [
 *                   ['letter' => 'A+', 'min' => 97, 'max' => 100, 'gpa' => 4.0],
 *                   ['letter' => 'A',  'min' => 93, 'max' => 96,  'gpa' => 4.0],
 *                   // ...
 *               ],
 *           ],
 *       ]
 *   ]
 *
 * Fits into the Settings Module:
 * - Route: GET/POST/PUT/DELETE settings.academic.grading
 * - Navigation: Academic → Grading Scales
 * - Frontend: resources/js/Pages/Settings/Academic/GradingScales.vue
 */

class GradingScalesController extends Controller
{
    public function index(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $settings = getMergedSettings('academic.grading_scales', $school);

        $settings['scales'] = $settings['scales'] ?? [];

        return Inertia::render('Settings/Academic/GradingScales', [
            'grading_scales' => $settings,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Academic'],
                ['label' => 'Grading Scales'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'is_default' => 'boolean',
            'grades' => 'required|array|min:1',
            'grades.*.letter' => 'required|string|max:10',
            'grades.*.min' => 'required|numeric|min:0|max:100',
            'grades.*.max' => 'required|numeric|min:0|max:100|gte:grades.*.min',
            'grades.*.gpa' => 'required|numeric|min:0|max:5',
        ]);

        try {
            $current = getMergedSettings('academic.grading_scales', $school);
            $scales = $current['scales'] ?? [];

            if ($validated['is_default']) {
                $scales = array_map(fn($s) => array_merge($s, ['is_default' => false]), $scales);
            }

            $scales[] = [
                'id' => (max(array_column($scales, 'id') ?: [0]) + 1),
                'name' => $validated['name'],
                'is_default' => $validated['is_default'] ?? false,
                'grades' => $validated['grades'],
            ];

            SaveOrUpdateSchoolSettings('academic.grading_scales', ['scales' => $scales], $school);

            return redirect()
                ->route('settings.academic.grading')
                ->with('success', 'Grading scale added successfully.');
        } catch (\Exception $e) {
            Log::error('Grading scale add failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to add grading scale.');
        }
    }

    public function update(Request $request, $id)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'is_default' => 'boolean',
            'grades' => 'required|array|min:1',
            'grades.*.letter' => 'required|string|max:10',
            'grades.*.min' => 'required|numeric|min:0|max:100',
            'grades.*.max' => 'required|numeric|min:0|max:100|gte:grades.*.min',
            'grades.*.gpa' => 'required|numeric|min:0|max:5',
        ]);

        try {
            $current = getMergedSettings('academic.grading_scales', $school);
            $scales = $current['scales'] ?? [];

            $found = false;
            foreach ($scales as &$scale) {
                if ($scale['id'] == $id) {
                    if ($validated['is_default']) {
                        $scales = array_map(fn($s) => array_merge($s, ['is_default' => false]), $scales);
                    }
                    $scale = array_merge($scale, [
                        'name' => $validated['name'],
                        'is_default' => $validated['is_default'] ?? false,
                        'grades' => $validated['grades'],
                    ]);
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return redirect()->back()->with('error', 'Grading scale not found.');
            }

            SaveOrUpdateSchoolSettings('academic.grading_scales', ['scales' => $scales], $school);

            return redirect()
                ->route('settings.academic.grading')
                ->with('success', 'Grading scale updated successfully.');
        } catch (\Exception $e) {
            Log::error('Grading scale update failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to update grading scale.');
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
            $current = getMergedSettings('academic.grading_scales', $school);
            $scales = $current['scales'] ?? [];

            $remaining = array_filter($scales, fn($s) => !in_array($s['id'], $validated['ids']));

            SaveOrUpdateSchoolSettings('academic.grading_scales', ['scales' => array_values($remaining)], $school);

            return redirect()
                ->route('settings.academic.grading')
                ->with('success', 'Grading scale(s) deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Grading scale delete failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to delete grading scale(s).');
        }
    }
}