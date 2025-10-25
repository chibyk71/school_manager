<?php

namespace App\Http\Controllers\Settings\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing user management settings in a single-tenant school system.
 */
class UserManagementController extends Controller
{
    /**
     * Display the user management settings.
     *
     * Retrieves user management settings for the active school and renders the view.
     *
     * @return \Inertia\Response The Inertia response with settings data.
     *
     * @throws \Exception If settings retrieval fails or no active school is found.
     */
    public function index()
    {
        try {
            permitted('manage-settings');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $settings = getMergedSettings('user_management', $school);

            return Inertia::render('Settings/UserManagement/General', [
                'settings' => $settings,
            ], 'resources/js/Pages/Settings/UserManagement/General.vue');
        } catch (\Exception $e) {
            Log::error('Failed to fetch user management settings: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load user management settings.');
        }
    }

    /**
     * Store or update user management settings.
     *
     * Validates and saves user management settings for the active school.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If settings storage fails.
     */
    public function store(Request $request)
    {
        try {
            permitted('manage-settings');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $validated = $request->validate([
                'online_admission' => 'required|boolean',
                'allow_student_signin' => 'required|boolean',
                'allow_parent_signin' => 'required|boolean',
                'allow_teacher_signin' => 'required|boolean',
                'allow_staff_signin' => 'required|boolean',
                'online_admission_fee' => 'required|numeric|min:0',
                'online_admission_instruction' => 'nullable|string|max:2000',
            ]);

            SaveOrUpdateSchoolSettings('user_management', $validated, $school);

            return redirect()
                ->route('settings.user-management.index')
                ->with('success', 'User management settings updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save user management settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save user management settings: ' . $e->getMessage());
        }
    }
}
