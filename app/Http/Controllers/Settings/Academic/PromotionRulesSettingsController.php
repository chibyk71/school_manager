<?php

namespace App\Http\Controllers\Settings\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * PromotionRulesSettingsController v1.0 – Production-Ready Promotion Policy Configuration
 *
 * Purpose:
 * Central configuration page for school-wide promotion rules used by the Promotion Module.
 *
 * This controller follows the exact same pattern as FeesSettingsController and the updated
 * AttendanceRulesController for consistency across the Settings module.
 *
 * Settings Key: 'academic.promotion_rules'
 *
 * Configurable Fields:
 *   • fail_subject_threshold (integer)     - Max failed subjects before auto "repeat"
 *   • pass_average (integer)               - Minimum average score to be eligible for promotion
 *   • probation_average (integer)          - Threshold below which a promoted student is on probation
 *
 * Default values (sensible for most Nigerian secondary schools):
 *   fail_subject_threshold: 3
 *   pass_average: 40
 *   probation_average: 45
 *
 * Why this controller exists:
 * - Allows each school to customize promotion criteria without code changes.
 * - Directly consumed by PromotionService when computing system recommendations.
 * - Supports the full recommendation logic:
 *     repeat → if failed_subjects >= fail_subject_threshold OR average < pass_average
 *     promote with probation → if pass_average <= average < probation_average
 *     promote normally → otherwise (subject to attendance rules)
 *     graduate → if final class level and meets criteria
 *
 * Fits into the Promotion Module:
 * - PromotionService::computeRecommendation() reads these values via getMergedSettings()
 * - Used during PopulatePromotionBatch job
 * - Frontend page: resources/js/Pages/Settings/Academic/PromotionRules.vue (to be built later)
 *
 * Features / Problems Solved:
 * - Uses getMergedSettings() + SaveOrUpdateSchoolSettings() → global defaults + school overrides
 * - No abort() → system admin can set platform-wide defaults
 * - Full validation with sensible ranges (e.g., averages between 0-100)
 * - Structured logging on failure
 * - Clean, consistent API with other academic settings controllers
 */

class PromotionRulesSettingsController extends Controller
{
    /**
     * Display the Promotion Rules settings page.
     */
    public function index(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $settings = getMergedSettings('academic.promotion_rules', $school);

        return Inertia::render('Settings/Academic/PromotionRules', [
            'settings' => $settings,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Academic'],
                ['label' => 'Promotion Rules'],
            ],
        ]);
    }

    /**
     * Store / Update promotion rules.
     */
    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'fail_subject_threshold' => 'required|integer|min:0|max:10',
            'pass_average' => 'required|integer|min:0|max:100',
            'probation_average' => 'required|integer|min:0|max:100',
        ]);

        // Additional business rule: probation_average should be >= pass_average
        if ($validated['probation_average'] < $validated['pass_average']) {
            return redirect()->back()
                ->with('error', 'Probation average must be greater than or equal to pass average.')
                ->withInput();
        }

        try {
            SaveOrUpdateSchoolSettings('academic.promotion_rules', $validated, $school);

            return redirect()
                ->route('settings.academic.promotion-rules')
                ->with('success', 'Promotion rules updated successfully.');
        } catch (\Exception $e) {
            Log::error('Promotion rules save failed', [
                'school_id' => $school?->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save promotion rules.')
                ->withInput();
        }
    }
}
