<?php

namespace App\Http\Controllers\Settings\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * AttendanceRulesController v2.0 – Updated for Promotion Module
 *
 * Purpose:
 * Central configuration for school-wide attendance rules and policies.
 * Now extended to support the Promotion Module with two new fields:
 *
 *   • use_attendance_for_promotion (boolean)
 *     Whether attendance percentage should be considered when computing promotion recommendations.
 *
 *   • promotion_min_attendance_percent (integer)
 *     Minimum attendance % required to avoid automatic "repeat" recommendation
 *     (only applied when use_attendance_for_promotion = true).
 *
 * Why these fields were added:
 * - Many Nigerian schools factor attendance heavily into promotion decisions.
 * - Allows per-school customization while keeping defaults sensible.
 * - Directly consumed by PromotionService when calculating recommendations.
 *
 * Settings Key: 'academic.attendance_rules'
 *
 * Updated Structure:
 *   'academic.attendance_rules' => [
 *       'minimum_percentage' => 75,
 *       'count_late_as_half_day' => true,
 *       'late_grace_minutes' => 15,
 *       'absent_after_minutes' => 120,
 *       'notify_parent_at_percentage' => 85,
 *       'mark_weekends_as_holiday' => true,
 *       'require_reason_for_absence' => true,
 *
 *       // NEW: Promotion integration
 *       'use_attendance_for_promotion' => true,
 *       'promotion_min_attendance_percent' => 75,
 *   ]
 *
 * Fits into the Promotion Module:
 * - PromotionService reads these values when building student recommendations.
 * - Default values are conservative and can be overridden per school.
 * - No breaking changes to existing attendance functionality.
 *
 * Features / Problems Solved:
 * - Uses getMergedSettings() + SaveOrUpdateSchoolSettings() → global defaults + school overrides
 * - No abort() → system admin can set platform defaults
 * - Full validation with sensible ranges for new promotion fields
 * - Clean grouped form with toggles and conditional fields (frontend will handle conditional UI)
 * - Production-ready: security, error handling, structured logs
 */

class AttendanceRulesController extends Controller
{
    public function index(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $settings = getMergedSettings('academic.attendance_rules', $school);

        return Inertia::render('Settings/Academic/AttendanceRules', [
            'settings' => $settings,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Academic'],
                ['label' => 'Attendance Rules'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'minimum_percentage' => 'required|integer|min:50|max:100',
            'count_late_as_half_day' => 'required|boolean',
            'late_grace_minutes' => 'required|integer|min:0|max:120',
            'absent_after_minutes' => 'required|integer|min:30|max:480',
            'notify_parent_at_percentage' => 'required|integer|min:50|max:100',
            'mark_weekends_as_holiday' => 'required|boolean',
            'require_reason_for_absence' => 'required|boolean',

            // ─── NEW: Promotion Module fields ─────────────────────────────
            'use_attendance_for_promotion' => 'required|boolean',
            'promotion_min_attendance_percent' => 'required|integer|min:50|max:100',
        ]);

        try {
            SaveOrUpdateSchoolSettings('academic.attendance_rules', $validated, $school);

            return redirect()
                ->route('settings.academic.attendance')
                ->with('success', 'Attendance rules updated successfully.');
        } catch (\Exception $e) {
            Log::error('Attendance rules save failed', [
                'school_id' => $school?->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save attendance rules.')
                ->withInput();
        }
    }
}
