<?php

namespace App\Http\Controllers\Settings\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * PromotionNotificationSettingsController v1.0 – Production-Ready Promotion Notifications Configuration
 *
 * Purpose:
 * Allows schools to configure which notification channels fire for each promotion-related event.
 *
 * Settings Key: 'academic.promotion_notifications'
 *
 * Structure (stored as nested array):
 *   'academic.promotion_notifications' => [
 *       'batch_ready_for_approval' => [
 *           'database' => true,
 *           'mail'     => true,
 *           'sms'      => false,
 *       ],
 *       'batch_approved' => [
 *           'database' => true,
 *           'mail'     => true,
 *           'sms'      => false,
 *       ],
 *       'batch_completed' => [
 *           'database' => true,
 *           'mail'     => true,
 *           'sms'      => true,
 *       ],
 *       'student_outcome' => [
 *           'database' => true,
 *           'mail'     => true,
 *           'sms'      => true,
 *       ],
 *   ]
 *
 * Fits into the Promotion Module:
 * - Listeners and Notifications read these preferences via getMergedSettings()
 * - Allows fine-grained control (e.g., SMS only for student outcomes)
 * - Uses existing SmsService, mail, and database notifications
 *
 * Follows exact pattern of FeesSettingsController and AttendanceRulesController.
 */

class PromotionNotificationSettingsController extends Controller
{
    /**
     * Display the Promotion Notification settings page.
     */
    public function index(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $settings = getMergedSettings('academic.promotion_notifications', $school);

        return Inertia::render('Settings/Academic/PromotionNotifications', [
            'settings' => $settings,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Academic'],
                ['label' => 'Promotion Notifications'],
            ],
        ]);
    }

    /**
     * Store / Update promotion notification preferences.
     */
    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'batch_ready_for_approval.database' => 'required|boolean',
            'batch_ready_for_approval.mail' => 'required|boolean',
            'batch_ready_for_approval.sms' => 'required|boolean',

            'batch_approved.database' => 'required|boolean',
            'batch_approved.mail' => 'required|boolean',
            'batch_approved.sms' => 'required|boolean',

            'batch_completed.database' => 'required|boolean',
            'batch_completed.mail' => 'required|boolean',
            'batch_completed.sms' => 'required|boolean',

            'student_outcome.database' => 'required|boolean',
            'student_outcome.mail' => 'required|boolean',
            'student_outcome.sms' => 'required|boolean',
        ]);

        try {
            SaveOrUpdateSchoolSettings('academic.promotion_notifications', $validated, $school);

            return redirect()
                ->route('settings.academic.promotion-notifications')
                ->with('success', 'Promotion notification preferences updated successfully.');
        } catch (\Exception $e) {
            Log::error('Promotion notification settings save failed', [
                'school_id' => $school?->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save promotion notification settings.')
                ->withInput();
        }
    }
}
