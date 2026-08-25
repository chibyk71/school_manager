<?php

namespace App\Policies\Student;

use App\Models\Academic\Student;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * StudentPolicy – Authorization for all Student-related Operations
 *
 * Covers the full lifecycle of a student record: CRUD, placement,
 * status changes, guardian management, and transfer operations.
 *
 * ── Permission Map ────────────────────────────────────────────────────────────
 * Map these in your RolesPermissionsSeeder:
 *
 *   students.view          → admin, academic, teacher, admissions
 *   students.create        → admin, admissions
 *   students.update        → admin, academic, admissions
 *   students.delete        → admin
 *   students.restore       → admin
 *   students.place         → admin, academic          (assign to class section)
 *   students.change-status → admin, academic          (activate/suspend/graduate)
 *   students.transfer      → admin                    (move between schools/sections)
 *   students.manage-guardians → admin, admissions     (add/remove/edit guardians)
 *
 * ── Multi-Tenant Safety ──────────────────────────────────────────────────────
 * Each resource-level method confirms the student belongs to the user's school.
 * This is a second line of defense after BelongsToSchool global scope.
 *
 * ── Super-Admin Bypass ───────────────────────────────────────────────────────
 * `before()` grants all abilities to super-admin unconditionally.
 *
 * ── Self-Service Rules ───────────────────────────────────────────────────────
 * A student user can view their own profile. All write operations require
 * explicit staff permissions — students cannot edit their own records.
 *
 * ── Fits into the Student Management Module ──────────────────────────────────
 * Used by StudentController, StudentPlacementController, StudentStatusController,
 * StudentGuardianController, and StudentTransferController.
 *
 * Register in AuthServiceProvider:
 *   Student::class => StudentPolicy::class
 */
class StudentPolicy
{
    use HandlesAuthorization;

    /**
     * Super-admin bypass.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return null;
    }

    /**
     * Can the user see the students list?
     * Used by StudentController::index()
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('students.view');
    }

    /**
     * Can the user view a specific student's profile?
     * Also allows a student to view their own record.
     * Used by StudentController::show()
     */
    public function view(User $user, Student $student): bool
    {
        // A student can view their own profile
        if ($this->isOwnProfile($user, $student)) {
            return true;
        }

        return $user->hasPermission('students.view')
            && $this->belongsToUserSchool($student);
    }

    /**
     * Can the user access the student creation form?
     * Used by StudentController::create()
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('students.create');
    }

    /**
     * Can the user store a new student?
     * Used by StudentController::store()
     */
    public function store(User $user): bool
    {
        return $user->hasPermission('students.create');
    }

    /**
     * Can the user update a student's core information?
     * Used by StudentController::update()
     */
    public function update(User $user, Student $student): bool
    {
        return $user->hasPermission('students.update')
            && $this->belongsToUserSchool($student);
    }

    /**
     * Can the user soft-delete a student?
     * Used by StudentController::destroy()
     */
    public function delete(User $user, Student $student): bool
    {
        return $user->hasPermission('students.delete')
            && $this->belongsToUserSchool($student);
    }

    /**
     * Can the user restore a soft-deleted student?
     * Used by StudentController::restore() if implemented.
     */
    public function restore(User $user, Student $student): bool
    {
        return $user->hasPermission('students.restore')
            && $this->belongsToUserSchool($student);
    }

    /**
     * Can the user permanently delete a student?
     * Force-delete is highly restricted — admin only as a safety measure.
     */
    public function forceDelete(User $user, Student $student): bool
    {
        return $user->hasRole('admin')
            && $this->belongsToUserSchool($student);
    }

    // ── Sub-resource abilities ────────────────────────────────────────────────

    /**
     * Can the user place a student into a class section?
     * Used by StudentPlacementController
     */
    public function place(User $user, Student $student): bool
    {
        return $user->hasPermission('students.place')
            && $this->belongsToUserSchool($student);
    }

    /**
     * Can the user change a student's enrollment status?
     * (activate, suspend, graduate, withdraw)
     * Used by StudentStatusController
     */
    public function changeStatus(User $user, Student $student): bool
    {
        return $user->hasPermission('students.change-status')
            && $this->belongsToUserSchool($student);
    }

    /**
     * Can the user transfer a student between sections or schools?
     * Used by StudentTransferController (if separate) or StudentController::transfer()
     */
    public function transfer(User $user, Student $student): bool
    {
        return $user->hasPermission('students.transfer')
            && $this->belongsToUserSchool($student);
    }

    /**
     * Can the user manage (add/edit/remove) a student's guardians?
     * Used by StudentGuardianController
     */
    public function manageGuardians(User $user, Student $student): bool
    {
        return $user->hasPermission('students.manage-guardians')
            && $this->belongsToUserSchool($student);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check the student record belongs to the currently active school.
     * Defends against URL manipulation targeting another tenant's student.
     */
    private function belongsToUserSchool(Student $student): bool
    {
        $activeSchool = GetSchoolModel();

        return $activeSchool !== null
            && $student->school_id === $activeSchool->id;
    }

    /**
     * Check if the authenticated user IS the student (self-service view).
     */
    private function isOwnProfile(User $user, Student $student): bool
    {
        // Student model has profile → profile has user_id
        return $user->id === $student->profile?->user_id;
    }
}
