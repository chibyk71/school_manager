<?php

namespace App\Services\Student;

use App\Models\Academic\Student;
use App\Models\Guardian;
use App\Services\DynamicEnum\DynamicEnumValidationStatus;
use App\Services\DynamicEnum\DynamicEnumValidator;
use App\Services\DynamicEnum\DynamicEnumValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * StudentGuardianService – Guardian-Student Relationship Management (v2.0 – Production-Ready)
 *
 * Handles all operations related to linking, updating, and removing guardians from a student
 * via the guardian_student pivot table.
 *
 * This service enforces important business rules:
 *   - Only one primary contact per student
 *   - Proper handling of emergency contact priority
 *   - Validation of relationship data
 *
 * Features / Problems Solved:
 * - Centralized logic for guardian-student pivot operations.
 * - Automatic demotion of previous primary contact when setting a new one.
 * - Transactional safety for all updates.
 * - Comprehensive logging for audit trails.
 * - Clean API for controllers and enrollment services.
 *
 * Fits into the Student Management Module:
 * - Used by StudentGuardianController and StudentEnrollmentService.
 * - Works with the guardian_student pivot and both Student & Guardian models.
 * - Called during enrollment and manual guardian management.
 */

class StudentGuardianService
{
    /**
     * Attach a guardian to a student with rich pivot data.
     */
    public function attachGuardian(Student $student, Guardian $guardian, array $pivotData): void
    {
        DB::transaction(function () use ($student, $guardian, $pivotData) {

            // If setting as primary, demote any existing primary contact
            if (!empty($pivotData['is_primary_contact'])) {
                $this->unsetPrimaryContact($student);
            }

            $relationship = $this->resolveCanonicalRelationship(
                $student,
                $pivotData['relationship'] ?? 'guardian',
            );

            $student->guardians()->attach($guardian->id, [
                'relationship' => $relationship,
                'is_primary_contact' => $pivotData['is_primary_contact'] ?? false,
                'can_pickup' => $pivotData['can_pickup'] ?? true,
                'can_access_portal' => $pivotData['can_access_portal'] ?? true,
                'is_emergency_contact' => $pivotData['is_emergency_contact'] ?? false,
                'emergency_contact_priority' => $pivotData['emergency_contact_priority'] ?? null,
                'notes' => $pivotData['notes'] ?? null,
            ]);

            Log::info('Guardian attached to student', [
                'student_id' => $student->id,
                'guardian_id' => $guardian->id,
                'relationship' => $relationship,
            ]);
        });
    }

    /**
     * Update pivot data for an existing guardian-student link.
     */
    public function updateGuardianLink(Student $student, Guardian $guardian, array $pivotData): void
    {
        DB::transaction(function () use ($student, $guardian, $pivotData) {

            // If changing primary contact status, handle demotion
            if (isset($pivotData['is_primary_contact']) && $pivotData['is_primary_contact']) {
                $this->unsetPrimaryContact($student);
            }

            $payload = [
                'is_primary_contact' => $pivotData['is_primary_contact'] ?? null,
                'can_pickup' => $pivotData['can_pickup'] ?? null,
                'can_access_portal' => $pivotData['can_access_portal'] ?? null,
                'is_emergency_contact' => $pivotData['is_emergency_contact'] ?? null,
                'emergency_contact_priority' => $pivotData['emergency_contact_priority'] ?? null,
                'notes' => $pivotData['notes'] ?? null,
            ];

            if (array_key_exists('relationship', $pivotData) && $pivotData['relationship'] !== null) {
                $payload['relationship'] = $this->resolveCanonicalRelationship(
                    $student,
                    $pivotData['relationship'],
                );
            }

            $student->guardians()->updateExistingPivot($guardian->id, array_filter(
                $payload,
                static fn ($v) => $v !== null,
            ));

            Log::info('Guardian link updated', [
                'student_id' => $student->id,
                'guardian_id' => $guardian->id,
            ]);
        });
    }

    /**
     * Remove a guardian from a student (delete pivot record).
     */
    public function detachGuardian(Student $student, Guardian $guardian): void
    {
        DB::transaction(function () use ($student, $guardian) {
            $student->guardians()->detach($guardian->id);

            Log::info('Guardian detached from student', [
                'student_id' => $student->id,
                'guardian_id' => $guardian->id,
            ]);
        });
    }

    /**
     * Set a specific guardian as the primary contact for the student.
     * Automatically demotes any previous primary contact.
     */
    public function setPrimaryContact(Student $student, Guardian $guardian): void
    {
        DB::transaction(function () use ($student, $guardian) {
            $this->unsetPrimaryContact($student);

            $student->guardians()->updateExistingPivot($guardian->id, [
                'is_primary_contact' => true,
            ]);

            Log::info('Primary contact set for student', [
                'student_id' => $student->id,
                'guardian_id' => $guardian->id,
            ]);
        });
    }

    /**
     * Private helper: Remove primary contact flag from all guardians of a student.
     */
    private function unsetPrimaryContact(Student $student): void
    {
        $student->guardians()->updateExistingPivot(null, [
            'is_primary_contact' => false,
        ]);
    }

    /**
     * Canonicalize and validate guardian_student.relationship via Dynamic Enum.
     *
     * Uses the student's school context when available; otherwise tenant baseline.
     *
     * @throws ValidationException
     */
    private function resolveCanonicalRelationship(Student $student, mixed $relationship): string
    {
        if (! is_string($relationship) || trim($relationship) === '') {
            throw ValidationException::withMessages([
                'relationship' => 'Guardian relationship is required.',
            ]);
        }

        $canonical = DynamicEnumValue::canonicalize($relationship);
        /** @var DynamicEnumValidator $validator */
        $validator = app(DynamicEnumValidator::class);

        $school = $student->school_id !== null ? $student->school : null;
        $result = $school !== null
            ? $validator->validate($school, 'guardian.relationship', $canonical)
            : $validator->validateTenant('guardian.relationship', $canonical);

        if ($result->isValid()) {
            return $canonical;
        }

        $message = match ($result->status) {
            DynamicEnumValidationStatus::DefinitionNotConfigured => 'Guardian relationship configuration is unavailable.',
            DynamicEnumValidationStatus::InactiveOption => 'The selected guardian relationship is no longer available.',
            default => 'The selected guardian relationship is invalid.',
        };

        throw ValidationException::withMessages([
            'relationship' => $message,
        ]);
    }
}
