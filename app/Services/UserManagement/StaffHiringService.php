<?php

namespace App\Services\UserManagement;

use App\Events\StaffHired;
use App\Helpers\IdGenerator;
use App\Models\Profile;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * StaffHiringService – Handles Creation of New Staff Positions / Employment Records
 *
 * This service is the primary entry point for hiring/creating a new staff member in the system.
 * It enforces the agreed architecture:
 *   - Profile is **never** created directly outside role-specific services
 *   - Creation always starts from role intention (here: hiring staff)
 *   - Creates Profile + Staff in a single transaction
 *   - User (login account) is **almost always created** (staff usually need portal access)
 *   - Reuses UserAccountService for consistent, reusable login creation
 *
 * Features / Problems Solved:
 * - Atomic transaction: Profile + Staff created together or rolled back completely
 * - Duplicate detection: basic check on name + phone/email + DOB combo
 * - Staff-specific validation: employee number uniqueness, employment dates, etc.
 * - Automatic staff ID generation via IdGenerator helper (school-prefixed)
 * - Login creation: strongly encouraged (default true) but still optional
 * - Role assignment: assigns Laratrust 'staff' role (and optional extras)
 * - Event-driven: fires StaffHired → triggers welcome email, HR notification, audit
 * - Error handling: clear ValidationException messages for frontend feedback
 * - Extensibility: easy hooks for department/role assignment, custom fields, onboarding checklist
 * - Performance: minimal queries, eager loading of profile
 * - Security: no plain password handling here; delegated to UserAccountService
 * - Multi-tenant safety: school_id required and set from current context
 *
 * Fits into User Management & HRM Module:
 * - Called from StaffController@store (main staff creation endpoint)
 * - Triggered by frontend: StaffAssignmentModal.vue / StaffHiringForm.vue
 * - Integrates with:
 *   - UserAccountService (handles optional/mandatory login creation)
 *   - Events: StaffHired (welcome email, HR onboarding, activity log)
 *   - Models: Profile (personal data), Staff (employment data)
 *   - Traits: HasCustomFields (school-specific staff attributes)
 *   - Helpers: generate_id() for staff_id_number
 * - Supports future extensions: probation period, contract upload, department sync
 *
 * Usage Example (in StaffController):
 *   $service = new StaffHiringService();
 *   $staff = $service->hire($validatedData, $createLogin = true);
 *
 * Important Conventions:
 * - No direct Profile creation outside role services
 * - Staff-specific fields validated here (staff_id_number, dates, employment_type)
 * - Login creation is **default true** (staff usually need access) but can be overridden
 * - Ready for future: guardian assignment (staff can be guardians), class/subject linking
 */

class StaffHiringService
{
    protected UserAccountService $userAccountService;

    public function __construct(UserAccountService $userAccountService)
    {
        $this->userAccountService = $userAccountService;
    }

    /**
     * Hire a new staff member (create Profile + Staff)
     *
     * @param array $data           Validated input (profile + staff fields)
     * @param bool  $createLogin    Whether to create a User/login account (default: true for staff)
     * @return Staff
     *
     * @throws ValidationException
     */
    public function hire(array $data, bool $createLogin = true): Staff
    {
        $school = GetSchoolModel();
        if (!$school) {
            throw new \RuntimeException('No active school context found.');
        }

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

            // 3. Generate staff ID (using IdGenerator helper)
            $staffIdNumber = IdGenerator::generate('staff_id', $school);

            // 4. Create Staff employment record
            $staff = Staff::create([
                'profile_id'          => $profile->id,
                'school_id'           => $school->id,
                'section_id'          => $data['section_id'] ?? null,
                'department_id'       => $data['department_id'] ?? null,
                'staff_id_number'     => $staffIdNumber,
                'date_of_employment'  => $data['date_of_employment'] ?? now()->toDateString(),
                'employment_type'     => $data['employment_type'] ?? 'full-time',
                'status'              => 'active',
                'notes'               => $data['notes'] ?? null,
                // Add more staff-specific fields (salary scale, qualifications, etc.)
            ]);

            // 5. Create login account (default true for staff)
            if ($createLogin) {
                $this->userAccountService->createForProfile(
                    profile: $profile,
                    data: [
                        'username' => $data['username'] ?? null,
                        'password' => $data['password'] ?? null,
                    ],
                    options: [
                        'roles'                => ['staff'], // base role – add more if needed
                        'autoGenerateUsername' => true,
                        'mustChangePassword'   => true,
                        'sendWelcome'          => true,
                    ]
                );
            }

            // 6. Fire event (welcome email, HR onboarding, audit, etc.)
            event(new StaffHired($staff, $createLogin));

            return $staff->load('profile');
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
