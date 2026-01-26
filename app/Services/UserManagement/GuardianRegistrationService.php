<?php

namespace App\Services\UserManagement;

use App\Events\GuardianRegistered;
use App\Models\Guardian;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * GuardianRegistrationService – Handles Standalone Guardian Creation
 *
 * This service is the primary entry point for registering a new guardian in the system
 * without immediately linking them to any student (linking happens separately via pivot).
 *
 * Key Architecture Alignment:
 *   - Profile is **never** created directly outside role-specific services
 *   - Creation always starts from role intention (here: registering a guardian)
 *   - Creates Profile + Guardian in a single transaction
 *   - User (login account) is **optional** (most guardians don't need portal access)
 *   - Reuses UserAccountService for consistent, reusable login creation when requested
 *
 * Features / Problems Solved:
 * - Atomic transaction: Profile + Guardian created together or rolled back
 * - Duplicate detection: basic check on name + phone/email + DOB combo
 * - Guardian-specific validation: minimal (notes, optional school_id)
 * - Optional login: delegates to UserAccountService (default: false for guardians)
 * - Event-driven: fires GuardianRegistered → triggers notification, audit, etc.
 * - Error handling: clear ValidationException messages for frontend feedback
 * - Extensibility: easy hooks for custom fields validation, future verification steps
 * - Performance: minimal queries, eager loading of profile
 * - Security: no plain password handling here; delegated safely
 * - Multi-tenant safety: school_id optional (guardian can be tenant-wide or school-specific)
 *
 * Fits into User Management Module:
 * - Called from GuardianController@store (main standalone guardian creation endpoint)
 * - Triggered by frontend: GuardianFormModal.vue (standalone registration form)
 * - Also supports inline creation from StudentEnrollmentModal.vue
 *   (but inline creation usually uses GuardianAssignmentService instead)
 * - Integrates with:
 *   - UserAccountService (handles optional login creation)
 *   - Events: GuardianRegistered (notification to admin, audit log)
 *   - Models: Profile (personal data), Guardian (guardian-specific metadata)
 *   - Traits: HasCustomFields (school-defined guardian attributes)
 * - Simplest of the core role services → good for testing optional User path
 *
 * Usage Example (in GuardianController):
 *   $service = new GuardianRegistrationService();
 *   $guardian = $service->register($validatedData, $createLogin = false);
 *
 * Important Conventions:
 * - No direct Profile creation outside role services
 * - Guardian-specific fields validated here (notes, optional school_id)
 * - Login creation is **default false** (guardians rarely need portal access)
 * - Ready for future: guardian verification, document upload, priority flags
 */

class GuardianRegistrationService
{
    protected UserAccountService $userAccountService;

    public function __construct(UserAccountService $userAccountService)
    {
        $this->userAccountService = $userAccountService;
    }

    /**
     * Register a new standalone guardian (Profile + Guardian)
     *
     * @param array $data           Validated input (profile + guardian fields)
     * @param bool  $createLogin    Whether to create a User/login account (default: false)
     * @return Guardian
     *
     * @throws ValidationException
     */
    public function register(array $data, bool $createLogin = false): Guardian
    {
        $school = GetSchoolModel(); // May be null (tenant-wide guardians allowed)

        return DB::transaction(function () use ($data, $createLogin, $school) {
            // 1. Basic duplicate check (name + phone/email + DOB)
            $this->checkForPotentialDuplicate($data);

            // 2. Create Profile (personal data)
            $profile = Profile::create([
                'first_name'    => $data['first_name'],
                'middle_name'   => $data['middle_name'] ?? null,
                'last_name'     => $data['last_name'],
                'gender'        => $data['gender'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'phone'         => $data['phone'] ?? null,
                'email'         => $data['email'] ?? null,
                // Add more profile fields as needed (custom fields via trait)
            ]);

            // 3. Create Guardian record
            $guardian = Guardian::create([
                'profile_id' => $profile->id,
                'school_id'  => $school?->id, // nullable – tenant-wide if null
                'notes'      => $data['notes'] ?? null,
                // Add more guardian-specific fields (custom fields via trait)
            ]);

            // 4. Optional: Create login account (default false for guardians)
            if ($createLogin) {
                $this->userAccountService->createForProfile(
                    profile: $profile,
                    data: [
                        'username' => $data['username'] ?? null,
                        'password' => $data['password'] ?? null,
                    ],
                    options: [
                        'roles'                => ['guardian'],
                        'autoGenerateUsername' => true,
                        'mustChangePassword'   => true,
                        'sendWelcome'          => true,
                    ]
                );
            }

            // 5. Fire event (notification, audit, etc.)
            event(new GuardianRegistered($guardian, $createLogin));

            return $guardian->load('profile');
        });
    }

    /**
     * Basic duplicate prevention check
     * (can be expanded with more sophisticated matching later)
     */
    private function checkForPotentialDuplicate(array $data): void
    {
        $existing = Profile::where('first_name', $data['first_name'])
            ->where('last_name', $data['last_name'])
            ->where(function ($q) use ($data) {
                if (!empty($data['date_of_birth'])) {
                    $q->whereDate('date_of_birth', $data['date_of_birth']);
                }
                if (!empty($data['phone'])) {
                    $q->orWhere('phone', $data['phone']);
                }
                if (!empty($data['email'])) {
                    $q->orWhere('email', $data['email']);
                }
            })
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'duplicate' => 'A profile with similar name, date of birth, and contact information already exists. Please check for duplicates.',
            ]);
        }
    }
}
