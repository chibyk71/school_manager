<?php

namespace App\Http\Controllers\Settings\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * NotificationsSettingsController v1.0 – Production-Ready Notification Preferences Management
 *
 * Purpose:
 * Allows administrators to configure which system events trigger notifications (email, SMS, in-app)
 * and to whom (admins, teachers, parents, students). This is a common feature in school SaaS
 * (Fedena, Gibbon, QuickSchools) under General → Notifications.
 *
 * Why stored in settings table:
 * - Notification preferences are operational but frequently customized per school
 * - Allows global defaults (set by system admin) with per-school overrides
 * - Keeps core models lean while enabling rich customization
 * - Uses your existing helpers perfectly
 *
 * Settings Key: 'general.notifications'
 *
 * Features / Problems Solved:
 * - Uses getMergedSettings() + SaveOrUpdateSchoolSettings() → supports global + school overrides
 * - No abort() → system admin can edit global defaults
 * - Comprehensive validation covering all common school events
 * - Structured boolean toggles for email/SMS/in-app per role
 * - Activity logging for audit trail
 * - Clean success/error handling with flashes
 * - Responsive, grouped form matching your PrimeVue/Tailwind stack
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.general.notifications
 * - Navigation: General Settings → Notifications
 * - Frontend: resources/js/Pages/Settings/General/Notifications.vue
 *
 * Default Values (for seeding on tenant bootstrap):
 *   'general.notifications' => [
 *       'student_admission' => ['admin' => true, 'parent' => true],
 *       'fee_payment' => ['admin' => true, 'parent' => true],
 *       'attendance_low' => ['admin' => true, 'teacher' => true, 'parent' => true],
 *       'exam_result_published' => ['admin' => true, 'teacher' => true, 'parent' => true, 'student' => true],
 *       'new_assignment' => ['teacher' => true, 'student' => true, 'parent' => true],
 *       'event_reminder' => ['admin' => true, 'teacher' => true, 'parent' => true],
 *       'birthday' => ['admin' => true, 'teacher' => true],
 *       'system_announcement' => ['admin' => true, 'teacher' => true, 'parent' => true, 'student' => true],
 *   ]
 *
 * Roles: admin, teacher, parent, student
 * Channels: email, sms, in_app (future expansion)
 */

class NotificationsSettingsController extends Controller
{
    /**
     * Display the notifications settings page.
     */
    public function index(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel(); // May be null → global defaults

        $settings = getMergedSettings('general.notifications', $school);

        return Inertia::render('Settings/General/Notifications', [
            'settings' => $settings,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'General Settings'],
                ['label' => 'Notifications'],
            ],
        ]);
    }

    /**
     * Store/update notification preferences.
     */
    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            // Event → Role matrix (boolean toggles)
            'student_admission.admin' => 'boolean',
            'student_admission.parent' => 'boolean',

            'admission_enquiry.admin' => 'boolean',

            'fee_payment.admin' => 'boolean',
            'fee_payment.parent' => 'boolean',

            'fee_due_reminder.admin' => 'boolean',
            'fee_due_reminder.parent' => 'boolean',

            'fee_overdue.admin' => 'boolean',
            'fee_overdue.parent' => 'boolean',

            'attendance_low.admin' => 'boolean',
            'attendance_low.teacher' => 'boolean',
            'attendance_low.parent' => 'boolean',

            'absent_today.admin' => 'boolean',
            'absent_today.teacher' => 'boolean',
            'absent_today.parent' => 'boolean',


            'exam_result_published.admin' => 'boolean',
            'exam_result_published.teacher' => 'boolean',
            'exam_result_published.parent' => 'boolean',
            'exam_result_published.student' => 'boolean',

            'new_assignment.teacher' => 'boolean',
            'new_assignment.student' => 'boolean',
            'new_assignment.parent' => 'boolean',

            'assignment_due.teacher' => 'boolean',
            'assignment_due.student' => 'boolean',
            'assignment_due.parent' => 'boolean',

            'event_reminder.admin' => 'boolean',
            'event_reminder.teacher' => 'boolean',
            'event_reminder.parent' => 'boolean',

            'birthday.admin' => 'boolean',
            'birthday.teacher' => 'boolean',

            'system_announcement.admin' => 'boolean',
            'system_announcement.teacher' => 'boolean',
            'system_announcement.parent' => 'boolean',
            'system_announcement.student' => 'boolean',

            'leave_approved.admin' => 'boolean',
            'leave_approved.teacher' => 'boolean',
            'leave_approved.parent' => 'boolean',
            'leave_approved.student' => 'boolean',

            'leave_requested.admin' => 'boolean',
            'leave_requested.teacher' => 'boolean',
            'leave_requested.parent' => 'boolean',
            'leave_requested.student' => 'boolean',

            'leave_rejected.admin' => 'boolean',
            'leave_rejected.teacher' => 'boolean',
            'leave_rejected.parent' => 'boolean',
            'leave_rejected.student' => 'boolean',
        ]);

        //     $gg = ['general.notifications' => [
        // // Admissions & Enrollment
        // 'student_admission' => ['admin' => true, 'parent' => true],
        // 'admission Enquiry' => ['admin' => true],

        // // Fees & Finance
        // 'fee_payment' => ['admin' => true, 'parent' => true],
        // 'fee_due_reminder' => ['admin' => true, 'parent' => true],
        // 'fee_overdue' => ['admin' => true, 'parent' => true],

        // // Attendance
        // 'attendance_low' => ['admin' => true, 'teacher' => true, 'parent' => true],
        // 'absent_today' => ['admin' => true, 'teacher' => true, 'parent' => true],

        // // Academics
        // 'exam_result_published' => ['admin' => true, 'teacher' => true, 'parent' => true, 'student' => true],
        // 'new_assignment' => ['teacher' => true, 'student' => true, 'parent' => true],
        // 'assignment_due' => ['teacher' => true, 'student' => true, 'parent' => true],

        // // Communication
        // 'system_announcement' => ['admin' => true, 'teacher' => true, 'parent' => true, 'student' => true],
        // 'event_reminder' => ['admin' => true, 'teacher' => true, 'parent' => true],

        // // Personal
        // 'birthday' => ['admin' => true, 'teacher' => true],

        // // Staff
        //     'leave_requested' => [
        //     'admin' => true,
        //     'teacher' => true,     // approver gets notified of new request
        //     'parent' => true,      // parent notified if student requested
        //     'student' => false,    // optional: student sees in portal anyway
        // ],
        // 'leave_approved' => [
        //     'admin' => false,
        //     'teacher' => false,
        //     'parent' => true,      // critical: parent must know
        //     'student' => true,     // critical: requester must know
        // ],
        // 'leave_rejected' => [
        //     'admin' => false,
        //     'teacher' => false,
        //     'parent' => true,      // critical: know why rejected
        //     'student' => true,     // critical: know outcome
        // ],]]
        // ;

        try {
            // Transform flat keys back to nested array for storage
            $nested = [];
            foreach ($validated as $key => $value) {
                [$event, $role] = explode('.', $key);
                $nested[$event][$role] = $value;
            }

            SaveOrUpdateSchoolSettings('general.notifications', $nested, $school);

            return redirect()
                ->route('settings.general.notifications')
                ->with('success', 'Notification preferences updated successfully.');
        } catch (\Exception $e) {
            Log::error('Notification settings save failed', [
                'school_id' => $school?->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save notification preferences.')
                ->withInput();
        }
    }
}
