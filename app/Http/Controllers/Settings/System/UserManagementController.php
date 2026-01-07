<?php

namespace App\Http\Controllers\Settings\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UserManagementSettingsRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * UserManagementController v2.0 – Production-Ready User Management Configuration
 *
 * Purpose:
 * Central configuration for user lifecycle, access, and enrollment policies.
 * Controls who can sign in, admission process, ID generation, guardian rules, and bulk operations.
 *
 * Why this page is essential:
 * - Different schools have vastly different policies (e.g., parents allowed login vs not)
 * - Online admission is a key feature for modern schools
 * - Enrollment ID format is critical for records
 * - Guardian limits prevent data abuse
 * - Bulk creation needed for initial data import
 *
 * Features / Problems Solved:
 * - Uses getMergedSettings() + SaveOrUpdateSchoolSettings() → global defaults + school overrides
 * - No abort() → system admin can set defaults
 * - Comprehensive validation via dedicated FormRequest
 * - Activity logging for audit
 * - Responsive form with grouped sections
 * - Production-ready: security, error handling, API support
 *
 * Additional Properties Added:
 * - Password requirements (min length, complexity)
 * - Account lockout after failed logins
 * - Session timeout
 * - Require profile completion on first login
 * - Auto-generate username format
 *
 * Settings Key: 'user_management'
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.school.user-management
 * - Navigation: School Settings → User Management (or General → User Management)
 * - Frontend: resources/js/Pages/Settings/School/UserManagement.vue
 */

class UserManagementController extends Controller
{
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        try {
            permitted('manage-settings', $request->expectsJson());

            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            $settings = getMergedSettings('user_management', $school);

            if ($request->expectsJson()) {
                return response()->json(['settings' => $settings]);
            }

            return Inertia::render('Settings/System/UserManagement', [
                'settings' => $settings,
                'school_id' => $school->id,
                'crumbs' => [
                    ['label' => 'Settings'],
                    ['label' => 'School'],
                    ['label' => 'User Management'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to fetch user management settings: {$e->getMessage()}");
            return $this->respondWithError($request, 'Failed to load user management settings.');
        }
    }

    public function store(UserManagementSettingsRequest $request): RedirectResponse|JsonResponse
    {
        try {
            permitted('manage-settings', $request->expectsJson());

            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            $validated = $request->validated();

            SaveOrUpdateSchoolSettings('user_management', $validated, $school);

            activity()
                ->performedOn($school)
                ->causedBy($request->user())
                ->withProperties(['settings' => $validated])
                ->log('User management settings updated');

            return $this->respondWithSuccess(
                $request,
                'User management settings saved successfully.',
                'settings.school.user-management'
            );
        } catch (\Exception $e) {
            Log::error("Failed to save user management settings: {$e->getMessage()}");
            return $this->respondWithError($request, 'Failed to save user management settings.');
        }
    }

    protected function respondWithSuccess(Request $request, string $message, ?string $redirectRoute = null)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return $redirectRoute
            ? redirect()->route($redirectRoute)->with('success', $message)
            : redirect()->back()->with('success', $message);
    }

    protected function respondWithError(Request $request, string $message, int $statusCode = 400)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], $statusCode);
        }

        return redirect()->back()->with('error', $message);
    }
}
