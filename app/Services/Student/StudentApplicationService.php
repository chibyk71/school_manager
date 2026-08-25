<?php

namespace App\Services\Student;

use App\Helpers\IdGenerator;
use App\Models\student\StudentApplication;
use App\Models\Student\Student;
use App\Models\Profile;
use App\Models\Guardian;
use App\Models\School;
use App\Models\User;
use App\Services\Student\StudentEnrollmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * StudentApplicationService – Core Business Logic for Student Applications (v2.0 – Production-Ready)
 *
 * This service handles the full lifecycle of student applications from submission
 * through review to admission (conversion into Profile + Student + Guardians).
 *
 * Responsibilities:
 * - Submit applications (public portal or admin direct)
 * - Review, reject, or admit applications
 * - Coordinate creation of Profile, Student, Guardian records, and pivot links
 * - Handle data mapping from application snapshot to canonical records
 * - Fire events and send notifications (to be extended)
 * - Maintain data integrity with database transactions
 *
 * Features / Problems Solved:
 * - Clean separation between raw application data and final student records.
 * - Secure admission flow: untrusted public data is validated and mapped properly.
 * - Supports both public_portal and admin_direct submission paths.
 * - Transactional admission process (all-or-nothing): Profile + Student + Placement + Guardians.
 * - Reuses StudentEnrollmentService for the actual enrollment step after admission.
 * - Comprehensive logging and error handling for production debugging.
 * - Prepares for events (ApplicationSubmitted, ApplicationAdmitted, ApplicationRejected).
 *
 * Fits into the Student Management Module:
 * - Primary service called by PublicApplicationController and ApplicationController.
 * - Works closely with StudentEnrollmentService (for the admission → enrollment step).
 * - Used in frontend flows: public application form, admin Applications/Show.vue actions (Admit/Reject buttons).
 * - Integrates with existing traits: HasDynamicEnum (status, gender), HasCustomFields (custom_data mapping).
 *
 * Usage Examples:
 *   $service->submitPublicApplication($validatedData, $school);
 *   $service->admitApplication($application, $placementData, $admin);
 *   $service->rejectApplication($application, $reason, $admin);
 */

class StudentApplicationService
{
    protected StudentEnrollmentService $enrollmentService;

    public function __construct(StudentEnrollmentService $enrollmentService)
    {
        $this->enrollmentService = $enrollmentService;
    }

    /**
     * Submit a new application from the public portal.
     *
     * @param array $data Validated application data
     * @param School $school Current school
     * @return StudentApplication
     * @throws ValidationException|\Exception
     */
    public function submitPublicApplication(array $data, School $school): StudentApplication
    {
        return DB::transaction(function () use ($data, $school) {
            $application = new StudentApplication();
            $application->fill($data);
            $application->school_id = $school->id;
            $application->source = 'public_portal';
            $application->status = 'pending';
            $application->submitted_at = now();
            $application->application_number = $application->generateApplicationNumber();
            $application->application_token = $application->generateToken();
            $application->save();

            Log::info('Public student application submitted', [
                'application_id' => $application->id,
                'school_id' => $school->id,
                'application_number' => $application->application_number,
            ]);

            // TODO: Fire ApplicationSubmitted event + send confirmation email/SMS

            return $application->fresh();
        });
    }

    /**
     * Submit an application directly by an admin.
     *
     * @param array $data Validated data
     * @param School $school
     * @param User $admin
     * @return StudentApplication
     */
    public function submitAdminApplication(array $data, School $school, User $admin): StudentApplication
    {
        return DB::transaction(function () use ($data, $school, $admin) {
            $application = new StudentApplication();
            $application->fill($data);
            $application->school_id = $school->id;
            $application->source = 'admin_direct';
            $application->status = $data['status'] ?? 'pending';
            $application->submitted_at = now();
            $application->reviewed_by = $admin->id;
            $application->reviewed_at = now();
            $application->application_number = $application->generateApplicationNumber();
            $application->application_token = $application->generateToken();
            $application->save();

            Log::info('Admin-created student application', [
                'application_id' => $application->id,
                'school_id' => $school->id,
                'admin_id' => $admin->id,
            ]);

            // If admin submits with 'admitted' status, proceed to admission automatically
            if ($application->status === 'admitted') {
                $this->admitApplication($application, [], $admin);
            }

            return $application->fresh();
        });
    }

    /**
     * Admit an application → create Profile + Student + Guardians + Placement
     *
     * @param StudentApplication $application
     * @param array $placementData Additional placement info (class, section, etc.)
     * @param User $admin
     * @return Student
     * @throws \Exception
     */
    public function admitApplication(StudentApplication $application, array $placementData, User $admin): Student
    {
        if ($application->status !== 'pending') {
            throw new \Exception('Only pending applications can be admitted.');
        }

        return DB::transaction(function () use ($application, $placementData, $admin) {
            // 1. Create or find Profile from application data
            $profile = Profile::firstOrCreate(
                [
                    'first_name' => $application->first_name,
                    'last_name' => $application->last_name,
                    'date_of_birth' => $application->date_of_birth,
                ],
                [
                    'middle_name' => $application->middle_name,
                    'gender' => $application->gender,
                    'phone' => $application->phone,
                    'email' => $application->email,
                    'notes' => "Created from application #{$application->application_number}",
                ]
            );

            // 2. Prepare data for StudentEnrollmentService
            $enrollmentData = array_merge($placementData, [
                'profile_id' => $profile->id,
                'admission_number' => $this->generateAdmissionNumber($application->school),
                'admission_date' => now()->toDateString(),
                'admission_type' => $application->previous_school ? 'transfer' : 'fresh',
                'status' => 'admitted',
                'application_id' => $application->id,
                'notes' => $application->admin_notes,
                // Custom fields from application will be mapped in enrollment service
                'custom_data' => $application->custom_data ?? [],
            ]);

            // 3. Use Enrollment Service to create Student + Placement + Guardians
            $student = $this->enrollmentService->enrollFromApplication($application, $enrollmentData, $admin);

            // 4. Link application to the new student
            $application->update([
                'status' => 'admitted',
                'student_id' => $student->id,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            Log::info('Application admitted successfully', [
                'application_id' => $application->id,
                'student_id' => $student->id,
                'profile_id' => $profile->id,
                'admin_id' => $admin->id,
            ]);

            // TODO: Fire ApplicationAdmitted event + notify guardian

            return $student;
        });
    }

    /**
     * Reject an application
     */
    public function rejectApplication(StudentApplication $application, string $reason, User $admin): bool
    {
        if ($application->status !== 'pending') {
            throw new \Exception('Only pending applications can be rejected.');
        }

        return DB::transaction(function () use ($application, $reason, $admin) {
            $application->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            Log::info('Application rejected', [
                'application_id' => $application->id,
                'reason' => $reason,
                'admin_id' => $admin->id,
            ]);

            // TODO: Fire ApplicationRejected event + notify applicant/guardian

            return true;
        });
    }

    /**
     * Generate admission number using school configuration (can be extended)
     */
    protected function generateAdmissionNumber(School $school): string
    {
        $year = now()->year;
        $adNo = IdGenerator::generate('student_id', $school, $year);
        return $adNo;
    }
}
