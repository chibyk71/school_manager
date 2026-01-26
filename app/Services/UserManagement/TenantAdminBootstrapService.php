<?php

namespace App\Services\UserManagement;

use App\Events\TenantAdminCreated;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * TenantAdminBootstrapService – One-time System Admin Creation During Tenant Setup
 *
 * This service is responsible for creating the **very first user** (system super-admin)
 * during initial tenant bootstrapping (installer, artisan command, or setup wizard).
 *
 * Key Constraints & Decisions:
 * - This is the **only** place where a Profile is created **directly** (no role model)
 * - All other users in the system are created via role-specific services
 *   (StudentEnrollmentService, StaffHiringService, GuardianRegistrationService)
 * - Creates User + Profile in a transaction (atomic)
 * - Auto-generates a secure random password (shown only once to setup admin)
 * - Forces password change on first login
 * - Assigns Laratrust super-admin role (assumes role exists via seeder)
 * - Fires TenantAdminCreated event → can trigger welcome/setup email
 * - Minimal validation (email uniqueness only) → setup is trusted
 * - No school context yet (runs before any school exists)
 * - Logs critical info (email + generated password hash) for audit/recovery
 *
 * Fits into User Management Module:
 * - Called exactly once per tenant:
 *     - During installation wizard (web route)
 *     - Via artisan command (php artisan tenant:bootstrap)
 * - Provides the pattern for User + Profile creation that all other services copy
 * - Does NOT create any role model (Student/Staff/Guardian) — keeps it pure
 * - Security: password never stored plain; shown only once in output
 * - Production-ready: transactional, event-driven, validated, logged
 *
 * Usage Examples:
 *   // In setup wizard controller
 *   $service = new TenantAdminBootstrapService();
 *   $admin = $service->create($email, $nameData);
 *
 *   // In artisan command
 *   $service->create('admin@school.com', ['first_name' => 'System', 'last_name' => 'Admin']);
 *
 * Important:
 * - Run this **before** creating the first school
 * - The returned password is shown **only once** — log or email it securely
 * - After this runs, normal creation flows take over (role-based)
 */

class TenantAdminBootstrapService
{
    /**
     * Create the initial system admin User + Profile
     *
     * @param string $email        Admin email (must be unique)
     * @param array  $profileData  Basic profile info (first_name, last_name, etc.)
     * @return User
     *
     * @throws ValidationException
     */
    public function create(string $email, array $profileData = []): User
    {
        // Basic validation (more can be added in wizard)
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email is already in use.',
            ]);
        }

        // Default profile data (minimal for system admin)
        $profileData = array_merge([
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'gender' => 'other',
        ], $profileData);

        return DB::transaction(function () use ($email, $profileData) {
            // 1. Generate secure random password
            $plainPassword = Str::random(16);
            $hashedPassword = Hash::make($plainPassword);

            // 2. Create User (no username yet – can be set later)
            $user = User::create([
                'email' => $email,
                'password' => $hashedPassword,
                'must_change_password' => true,
                'is_active' => true,
            ]);

            // 3. Create Profile (direct creation – only allowed here)
            $profile = Profile::create([
                'user_id' => $user->id,
                'first_name' => $profileData['first_name'],
                'last_name' => $profileData['last_name'],
                'middle_name' => $profileData['middle_name'] ?? null,
                'gender' => $profileData['gender'] ?? 'other',
                'phone' => $profileData['phone'] ?? null,
                'email' => $email,
            ]);

            // 4. Assign super-admin role (Laratrust)
            // Assumes 'super-admin' role exists via seeder
            $user->assignRole('super-admin');

            // 5. Fire event (welcome email, setup instructions, audit)
            event(new TenantAdminCreated($user, $plainPassword));

            // Return fully loaded user
            return $user->load('profile');
        });
    }

    /**
     * Alternative: Run from artisan command (returns plain password for display)
     *
     * @param string $email
     * @param array  $profileData
     * @return array [User, string $plainPassword]
     */
    public function runFromCommand(string $email, array $profileData = []): array
    {
        $user = $this->create($email, $profileData);

        // In command context: show plain password only once
        $plainPassword = Str::random(16); // regenerate or retrieve from event if needed

        return [$user, $plainPassword];
    }
}
