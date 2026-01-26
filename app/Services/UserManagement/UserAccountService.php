<?php

namespace App\Services\UserManagement;

use App\Events\UserAccountCreated;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * UserAccountService – Reusable Service for Creating Optional Login Accounts
 *
 * This is the cross-cutting helper service responsible for creating a User (login account)
 * linked to an **existing** Profile — after the Profile has already been created via a role-specific flow.
 *
 * Core Constraint Alignment:
 *   - Never creates Profiles — assumes Profile already exists (enforced by constructor)
 *   - Called optionally from role services (StudentEnrollmentService, StaffHiringService, etc.)
 *     when the UI checkbox "Create login account" is checked
 *   - Supports all role types (student, staff, guardian) without role-specific logic
 *   - Username is primary login identifier (email is only on Profile)
 *   - Password auto-generated when not provided + must_change_password flag
 *   - Laratrust roles assigned during creation (passed from caller)
 *   - Fires UserAccountCreated event → triggers welcome email, SMS, audit log, etc.
 *
 * Features / Problems Solved:
 * - Reusable across all role creation flows → no duplicated login logic
 * - Secure defaults: strong random password, must_change_password enforced
 * - Username flexibility: provided → auto-generated (settings-aware) → email prefix fallback
 * - Atomic transaction: User creation + Profile linking + role assignment
 * - Validation: username uniqueness, basic password rules
 * - Event-driven: decouples notification/audit from creation logic
 * - Error handling: clear ValidationException messages for frontend
 * - Performance: minimal queries, eager loading of profile
 * - Extensibility: easy to add 2FA setup, approval workflow, custom fields
 * - Multi-tenant safety: inherits context from Profile (no direct school_id here)
 *
 * Fits into User Management Module:
 * - Used by:
 *   - StudentEnrollmentService (rare – only if student login enabled)
 *   - StaffHiringService (very common – most staff need portal access)
 *   - GuardianRegistrationService (optional – only if guardian needs portal)
 *   - Admin user creation screens (bulk or manual account setup)
 * - Triggered from frontend modals:
 *   - StudentEnrollmentModal.vue → "Create student portal account" checkbox
 *   - StaffAssignmentModal.vue → "Grant staff login access" toggle
 * - Integrates with:
 *   - Laratrust: syncRoles()
 *   - Events: UserAccountCreated → welcome email, notification
 *   - Settings: future auto-generate username toggle (via school settings)
 * - No direct UI — pure backend service; returns User model for response
 *
 * Usage Example (in StudentEnrollmentService):
 *   if ($createLogin) {
 *       $this->userAccountService->createForProfile(
 *           $profile,
 *           ['username' => $data['username'] ?? null],
 *           ['roles' => ['student']]
 *       );
 *   }
 */

class UserAccountService
{
    /**
     * Create a User login account linked to an existing Profile
     *
     * @param Profile $profile       Existing profile to attach login to
     * @param array   $data          Input: username, password, etc.
     * @param array   $options       Flags: roles, autoGenerateUsername, etc.
     * @return User
     *
     * @throws ValidationException
     */
    public function createForProfile(
        Profile $profile,
        array $data = [],
        array $options = []
    ): User {
        $options = array_merge([
            'roles'                => [],
            'autoGenerateUsername' => true,
            'mustChangePassword'   => true,
            'sendWelcome'          => true,
        ], $options);

        // 1. Resolve username
        $username = $this->resolveUsername($profile, $data, $options['autoGenerateUsername']);

        // 2. Validate uniqueness
        if (User::where('username', $username)->exists()) {
            throw ValidationException::withMessages([
                'username' => 'This username is already taken.',
            ]);
        }

        // 3. Password handling
        $plainPassword = $data['password'] ?? Str::random(12);
        if (strlen($plainPassword) < 8) {
            throw ValidationException::withMessages([
                'password' => 'Password must be at least 8 characters.',
            ]);
        }

        return DB::transaction(function () use (
            $profile,
            $username,
            $plainPassword,
            $options
        ) {
            // Create User
            $user = User::create([
                'username'             => $username,
                'password'             => Hash::make($plainPassword),
                'must_change_password' => $options['mustChangePassword'],
                'is_active'            => true,
            ]);

            // Link Profile to User (1:1)
            $profile->update(['user_id' => $user->id]);

            // Assign Laratrust roles (passed from role service)
            if (!empty($options['roles'])) {
                $user->syncRoles($options['roles']);
            }

            // Fire event (welcome email, audit, etc.)
            if ($options['sendWelcome']) {
                event(new UserAccountCreated(
                    user: $user,
                    plainPassword: $plainPassword,
                    autoGenerated: empty($data['password'])
                ));
            }

            return $user->load('profile');
        });
    }

    /**
     * Determine username: priority order
     * 1. Provided in $data
     * 2. Auto-generate (school settings allowing)
     * 3. Email prefix fallback
     */
    private function resolveUsername(Profile $profile, array $data, bool $allowAutoGenerate): string
    {
        // 1. Explicitly provided
        if (!empty($data['username'])) {
            return $data['username'];
        }

        // 2. Auto-generate if allowed
        if ($allowAutoGenerate) {
            $prefix = strtolower(substr($profile->first_name ?? 'user', 0, 1) . $profile->last_name);
            $prefix = preg_replace('/[^a-z0-9]/i', '', $prefix);
            return $prefix . '_' . Str::random(4);
        }

        // 3. Fallback: email prefix
        $email = $profile->email;
        if ($email) {
            $prefix = Str::before($email, '@');
            $prefix = preg_replace('/[^a-z0-9]/i', '', $prefix);
            return $prefix . '_' . Str::random(3);
        }

        throw ValidationException::withMessages([
            'username' => 'Unable to determine username. Please provide one manually.',
        ]);
    }

    /**
     * Reset password for existing user (admin or self-service)
     *
     * @param User $user
     * @param bool $notify
     * @return string New plain password (for display/email)
     */
    public function resetPassword(User $user, bool $notify = true): string
    {
        $newPassword = Str::random(12);

        $user->update([
            'password'             => Hash::make($newPassword),
            'must_change_password' => true,
        ]);

        if ($notify) {
            event(new UserAccountCreated($user, $newPassword, true)); // reuse event or create new
        }

        return $newPassword;
    }
}
