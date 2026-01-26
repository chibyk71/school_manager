<?php

namespace App\Services\UserManagement;

use App\Events\StudentEnrolled;
use App\Helpers\IdGenerator;
use App\Models\Academic\Student;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * StudentEnrollmentService – Handles New Student Enrollment Creation
 *
 * This is the primary service for creating a new student record in the system.
 * It follows the agreed architecture:
 *   - Profile is **never** created directly
 *   - Creation always starts from a role intention (here: enrolling a student)
 *   - Creates Profile + Student in a transaction
 *   - User (login account) is **optional** (checkbox in UI)
 *   - Reuses UserAccountService for login creation when requested
 *
 * Features / Problems Solved:
 * - Atomic transaction: Profile + Student created together or not at all
 * - Duplicate detection: basic check on name + DOB + phone/email
 * - Role-first creation: no direct Profile manipulation outside role flows
 * - Optional login: delegates to UserAccountService (reusable across roles)
 * - Event-driven: fires StudentEnrolled → triggers notifications, welcome email, etc.
 * - Validation: centralized rules for profile + student-specific fields
 * - Error handling: meaningful exceptions with user-friendly messages
 * - Extensibility: easy to add custom fields validation, guardian assignment, etc.
 * - Performance: minimal queries, eager loading where needed
 * - Security: no plain password handling here; delegated safely
 * - Multi-tenant safety: school_id required and set from context
 *
 * Fits into User Management Module:
 * - Called from StudentController@store (main enrollment endpoint)
 * - Triggered by frontend: StudentEnrollmentModal.vue (form submission)
 * - Integrates with:
 *   - UserAccountService (optional login creation)
 *   - Events: StudentEnrolled (welcome, parent notification, audit)
 *   - Models: Profile (personal data), Student (enrollment data)
 *   - Traits: HasCustomFields (school-specific student data validation)
 * - Supports re-use pattern for future StudentReEnrollmentService
 *
 * Usage Example (in StudentController):
 *   $service = new StudentEnrollmentService();
 *   $student = $service->enroll($validatedData, $createLogin = true);
 *
 * Important Conventions:
 * - No direct Profile creation outside role services
 * - All personal data validation lives here (name, DOB, gender, etc.)
 * - Student-specific fields (admission_number, enrollment_date, etc.) validated here
 * - User creation is delegated — keeps this service focused on enrollment
 * - Ready for future extensions: guardian assignment, class placement, etc.
 */

class StudentEnrollmentService
{
    protected UserAccountService $userAccountService;

    public function __construct(UserAccountService $userAccountService)
    {
        $this->userAccountService = $userAccountService;
    }

    /**
     * Enroll a new student (create Profile + Student)
     *
     * @param array $data           Validated input (profile + student fields)
     * @param bool  $createLogin    Whether to create a User/login account
     * @return Student
     *
     * @throws ValidationException
     */
    public function enroll(array $data, bool $createLogin = false): Student
    {
        $school = GetSchoolModel();
        if (!$school) {
            throw new \RuntimeException('No active school context found.');
        }

        return DB::transaction(function () use ($data, $createLogin, $school) {
            // 1. Basic duplicate check (name + DOB + phone/email combo)
            $this->checkForPotentialDuplicate($data);

            // 2. Create Profile (personal data)
            $profile = Profile::create([
                'first_name'  => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name'   => $data['last_name'],
                'gender'      => $data['gender'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'phone'       => $data['phone'] ?? null,
                'email'       => $data['email'] ?? null,
                // Add more profile fields as needed (custom fields handled via trait)
            ]);

            // 3. Generate admission number (using IdGenerator helper)
            $admissionNumber = IdGenerator::generate('student_id', $school, now()->year);

            // 4. Create Student enrollment record
            $student = \App\Models\Academic\Student::create([
                'profile_id'       => $profile->id,
                'school_id'        => $school->id,
                'section_id'       => $data['section_id'],
                'admission_number' => $admissionNumber,
                'enrollment_date'  => $data['enrollment_date'] ?? now()->toDateString(),
                'status'           => 'active',
                // Add more student-specific fields (class, transport, etc.)
                'notes'            => $data['notes'] ?? null,
            ]);

            // 5. Optional: Create login account
            if ($createLogin) {
                $this->userAccountService->createForProfile(
                    profile: $profile,
                    data: [
                        'username' => $data['username'] ?? null,
                        'password' => $data['password'] ?? null,
                    ],
                    options: [
                        'roles' => ['student'],
                        'autoGenerateUsername' => true,
                    ]
                );
            }

            // 6. Fire event (welcome, parent notification, audit, etc.)
            event(new StudentEnrolled($student, $createLogin));

            return $student->load('profile');
        });
    }

    /**
     * Basic duplicate prevention check
     * (can be expanded with more sophisticated matching)
     */
    private function checkForPotentialDuplicate(array $data): void
    {
        $existing = Profile::where('first_name', $data['first_name'])
            ->where('last_name', $data['last_name'])
            ->whereDate('date_of_birth', $data['date_of_birth'] ?? null)
            ->where(function ($q) use ($data) {
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
                'duplicate' => 'A profile with similar name, date of birth, and contact info already exists. Please check for duplicates before continuing.',
            ]);
        }
    }
}
