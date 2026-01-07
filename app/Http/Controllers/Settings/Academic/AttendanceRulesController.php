<?php

namespace App\Http\Controllers\Settings\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * AttendanceRulesController v1.0 – Production-Ready Attendance Policy Configuration
 *
 * Purpose:
 * Central configuration for school-wide attendance rules and policies:
 * - Minimum attendance percentage required for promotion/exams
 * - Late arrival grace period
 * - Absent vs late vs half-day definitions
 * - Notification thresholds (low attendance alerts)
 * - Weekend/holiday attendance rules
 *
 * Why this page is essential:
 * - Attendance policy varies greatly between schools (e.g., 75% vs 90% minimum)
 * - Directly affects student eligibility for exams/promotion
 * - Triggers notifications (parents alerted at thresholds)
 * - Industry standard: most school systems have dedicated "Attendance Settings"
 *
 * Features / Problems Solved:
 * - Uses getMergedSettings() + SaveOrUpdateSchoolSettings() → global defaults + school overrides
 * - No abort() → system admin can set platform defaults
 * - Full validation with sensible ranges
 * - Clean grouped form with toggles and conditional fields
 * - Responsive PrimeVue layout
 * - Production-ready: security, error handling, structured logs
 *
 * Settings Key: 'academic.attendance_rules'
 *
 * Structure:
 *   'academic.attendance_rules' => [
 *       'minimum_percentage' => 75,
 *       'count_late_as_half_day' => true,
 *       'late_grace_minutes' => 15,
 *       'absent_after_minutes' => 120,
 *       'notify_parent_at_percentage' => 85,
 *       'mark_weekends_as_holiday' => true,
 *       'require_reason_for_absence' => true,
 *   ]
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.academic.attendance
 * - Navigation: Academic → Attendance Rules
 * - Frontend: resources/js/Pages/Settings/Academic/AttendanceRules.vue
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