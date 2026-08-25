<?php

namespace App\Services\Student;

use App\Models\Student\Student;
use App\Models\Student\StudentApplication;
use App\Models\Student\StudentSessionPlacement;
use App\Models\Profile;
use App\Models\Guardian;
use App\Models\School;
use App\Models\User;
use App\Services\UserManagement\UserAccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * StudentEnrollmentService – Core Enrollment & Admission Business Logic (v2.0 – Production-Ready)
 *
 * This service handles the complete student enrollment process, whether coming from:
 *   - Multi-step Enrollment Wizard (direct enrollment)
 *   - Student Application admission flow
 *
 * It coordinates creation/update of Profile, Student, Guardian links, and Session Placement
 * in a single atomic transaction to maintain data integrity.
 *
 * Features / Problems Solved:
 * - Unified enrollment logic for both wizard and application admission paths.
 * - Strict validation at each step (has profile, has placement, has at least one guardian).
 * - Clean separation of concerns: this service focuses on enrollment; StudentApplicationService handles application-specific logic.
 * - Full transactional safety — rollback everything if any step fails.
 * - Proper delegation to UserAccountService for portal access creation.
 * - Comprehensive logging for production debugging and audit trails.
 * - Prepares for events (StudentEnrolled) and future notifications.
 *
 * Fits into the Student Management Module:
 * - Called by StudentApplicationService::admitApplication() and StudentController (wizard).
 * - Works closely with StudentPlacementService and StudentGuardianService.
 * - Used in frontend: EnrollmentWizard (multi-step), Application admit action.
 * - Integrates with existing traits: HasDynamicEnum (status, admission_type), HasCustomFields, HasAddress (via Profile).
 */

class StudentEnrollmentService
{
    protected UserAccountService $userAccountService;

    public function __construct(UserAccountService $userAccountService)
    {
        $this->userAccountService = $userAccountService;
    }

    /**
     * Enroll a new student from the multi-step Enrollment Wizard.
     *
     * @param array $wizardData All data collected from the 5–6 step wizard
     * @param School $school Current school context
     * @return Student
     * @throws ValidationException|\Exception
     */
    public function enrollFromWizard(array $wizardData, School $school): Student
    {
        return DB::transaction(function () use ($wizardData, $school) {

            // Step 1: Personal Info → Create/Update Profile
            $profile = $this->createOrUpdateProfile($wizardData['personal'] ?? []);

            // Step 2: Create Student record
            $studentData = array_merge($wizardData['enrollment'] ?? [], [
                'profile_id' => $profile->id,
                'school_id' => $school->id,
                'admission_date' => now()->toDateString(),
                'status' => 'admitted',
            ]);

            $student = Student::create($studentData);

            // Step 3: Create/Link Guardians
            if (!empty($wizardData['guardians'])) {
                $this->attachGuardians($student, $wizardData['guardians']);
            }

            // Step 4: Create Initial Placement
            if (!empty($wizardData['placement'])) {
                $this->createInitialPlacement($student, $wizardData['placement']);
            }

            // Step 5: Optional Portal Account for Guardian / Parent
            if (!empty($wizardData['portal_access']) && $wizardData['portal_access']['create_account'] === true) {
                // We usually create account for the primary guardian, not the student
                $primaryGuardian = $student->primaryGuardian();
                if ($primaryGuardian && $primaryGuardian->profile) {
                    $this->userAccountService->createForProfile(
                        $primaryGuardian->profile,
                        [
                            'username' => $wizardData['portal_access']['username'] ?? null,
                            'password' => $wizardData['portal_access']['password'] ?? null,
                        ],
                        [
                            'roles' => ['guardian'],   // or 'parent'
                            'mustChangePassword' => true,
                            'sendWelcome' => true,
                        ]
                    );
                }
            }

            // Step 6: Complete Enrollment (move from admitted → enrolled)
            $this->completeEnrollment($student);

            Log::info('Student enrolled from wizard successfully', [
                'student_id' => $student->id,
                'profile_id' => $profile->id,
                'school_id' => $school->id,
            ]);

            // TODO: Fire StudentEnrolled event + send welcome notifications

            return $student->fresh(['profile', 'currentPlacement', 'guardians']);
        });
    }

    /**
     * Enroll a student from an approved StudentApplication.
     * Called by StudentApplicationService::admitApplication().
     */
    public function enrollFromApplication(StudentApplication $application, array $extraData, User $admin): Student
    {
        return DB::transaction(function () use ($application, $extraData, $admin) {

            // Create/Update Profile from application snapshot
            $profileData = [
                'first_name' => $application->first_name,
                'middle_name' => $application->middle_name,
                'last_name' => $application->last_name,
                'date_of_birth' => $application->date_of_birth,
                'gender' => $application->gender,
                'phone' => $application->phone,
                'email' => $application->email,
                'notes' => "Created from application {$application->application_number}",
            ];

            $profile = Profile::firstOrCreate(
                ['first_name' => $application->first_name, 'last_name' => $application->last_name, 'date_of_birth' => $application->date_of_birth],
                $profileData
            );

            // Prepare Student data
            $studentData = array_merge($extraData, [
                'profile_id' => $profile->id,
                'school_id' => $application->school_id,
                'application_id' => $application->id,
                'admission_date' => now()->toDateString(),
                'status' => 'admitted',
            ]);

            $student = Student::create($studentData);

            // Attach guardians from application guardians_data JSON
            if (!empty($application->guardians_data)) {
                $this->attachGuardiansFromApplication($student, $application->guardians_data);
            }

            // Create initial placement
            $placementData = $extraData['placement'] ?? [];
            if (!empty($placementData)) {
                $this->createInitialPlacement($student, $placementData);
            }

            // Complete enrollment (admitted → enrolled)
            $this->completeEnrollment($student);

            Log::info('Student enrolled from application', [
                'application_id' => $application->id,
                'student_id' => $student->id,
                'profile_id' => $profile->id,
            ]);

            return $student->fresh(['profile', 'currentPlacement', 'guardians']);
        });
    }

    /**
     * Finalize enrollment: move status from 'admitted' to 'enrolled'
     * Validates that the student has required components.
     */
    public function completeEnrollment(Student $student): Student
    {
        // Validation
        if (!$student->profile) {
            throw new \Exception('Cannot complete enrollment: Profile is missing.');
        }

        if (!$student->currentPlacement) {
            throw new \Exception('Cannot complete enrollment: No academic placement assigned.');
        }

        if ($student->guardians()->count() === 0) {
            throw new \Exception('Cannot complete enrollment: At least one guardian is required.');
        }

        // Update status
        $student->update([
            'status' => 'enrolled',
            'status_date' => now()->toDateString(),
        ]);

        // Ensure current placement is marked properly
        $student->currentPlacement->update(['is_current' => true]);

        Log::info('Student enrollment completed', [
            'student_id' => $student->id,
            'status' => 'enrolled',
        ]);

        return $student->fresh();
    }

    // =================================================================
    // Private Helper Methods
    // =================================================================

    private function createOrUpdateProfile(array $personalData): Profile
    {
        return Profile::updateOrCreate(
            [
                'first_name' => $personalData['first_name'],
                'last_name' => $personalData['last_name'],
                'date_of_birth' => $personalData['date_of_birth'] ?? null,
            ],
            [
                'middle_name' => $personalData['middle_name'] ?? null,
                'gender' => $personalData['gender'] ?? null,
                'phone' => $personalData['phone'] ?? null,
                'email' => $personalData['email'] ?? null,
                'notes' => $personalData['notes'] ?? null,
            ]
        );
    }

    private function attachGuardians(Student $student, array $guardiansData): void
    {
        foreach ($guardiansData as $guardianData) {
            $profile = $this->createOrUpdateProfile($guardianData['personal'] ?? []);

            $guardian = Guardian::firstOrCreate(
                ['profile_id' => $profile->id],
                ['school_id' => $student->school_id, 'notes' => $guardianData['notes'] ?? null]
            );

            $student->guardians()->attach($guardian->id, [
                'relationship' => $guardianData['relationship'] ?? 'guardian',
                'is_primary_contact' => $guardianData['is_primary_contact'] ?? false,
                'can_pickup' => $guardianData['can_pickup'] ?? true,
                'can_access_portal' => $guardianData['can_access_portal'] ?? true,
                'is_emergency_contact' => $guardianData['is_emergency_contact'] ?? false,
                'emergency_contact_priority' => $guardianData['emergency_contact_priority'] ?? null,
                'notes' => $guardianData['notes'] ?? null,
            ]);
        }
    }

    private function attachGuardiansFromApplication(Student $student, array $guardiansData): void
    {
        foreach ($guardiansData as $g) {
            // For now we create minimal guardian records from application data
            // In a full implementation you may want to match existing guardians by phone/name
            $profile = Profile::firstOrCreate(
                ['phone' => $g['phone'] ?? null],
                [
                    'first_name' => $g['name'] ?? 'Unknown',
                    'last_name' => '',
                    'phone' => $g['phone'] ?? null,
                    'email' => $g['email'] ?? null,
                ]
            );

            $guardian = Guardian::firstOrCreate(
                ['profile_id' => $profile->id],
                ['school_id' => $student->school_id]
            );

            $student->guardians()->attach($guardian->id, [
                'relationship' => $g['relationship'] ?? 'guardian',
                'is_primary_contact' => $g['is_primary'] ?? false,
                'can_pickup' => true,
                'can_access_portal' => true,
            ]);
        }
    }

    private function createInitialPlacement(Student $student, array $placementData): StudentSessionPlacement
    {
        return StudentSessionPlacement::create([
            'student_id' => $student->id,
            'academic_session_id' => $placementData['academic_session_id'],
            'class_level_id' => $placementData['class_level_id'],
            'class_section_id' => $placementData['class_section_id'] ?? null,
            'enrolled_at' => now()->toDateString(),
            'is_current' => true,
            'promotion_outcome' => 'fresh_admission',
            'notes' => $placementData['notes'] ?? null,
        ]);
    }
}
