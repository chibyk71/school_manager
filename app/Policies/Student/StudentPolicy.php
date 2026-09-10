<?php

namespace App\Policies\Student;

use App\Models\Student\Student;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * StudentPolicy – Authorization for all Student-related Operations
 *
 * Covers the full lifecycle of a student record: CRUD, placement,
 * status changes, guardian management, and transfer operations.
 *
 * Permission map: students.view/create/update/delete/restore/place/
 * change-status/transfer/manage-guardians
 *
 * Multi-tenant: resource methods confirm student belongs to active school.
 * Super-admin bypass via before().
 */
class StudentPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('students.view');
    }

    public function view(User $user, Student $student): bool
    {
        if ($this->isOwnProfile($user, $student)) {
            return true;
        }

        return $user->hasPermission('students.view')
            && $this->belongsToUserSchool($student);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('students.create');
    }

    public function store(User $user): bool
    {
        return $user->hasPermission('students.create');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->hasPermission('students.update')
            && $this->belongsToUserSchool($student);
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->hasPermission('students.delete')
            && $this->belongsToUserSchool($student);
    }

    public function restore(User $user, Student $student): bool
    {
        return $user->hasPermission('students.restore')
            && $this->belongsToUserSchool($student);
    }

    public function forceDelete(User $user, Student $student): bool
    {
        return $user->hasRole('admin')
            && $this->belongsToUserSchool($student);
    }

    public function place(User $user, Student $student): bool
    {
        return $user->hasPermission('students.place')
            && $this->belongsToUserSchool($student);
    }

    public function changeStatus(User $user, Student $student): bool
    {
        return $user->hasPermission('students.change-status')
            && $this->belongsToUserSchool($student);
    }

    public function transfer(User $user, Student $student): bool
    {
        return $user->hasPermission('students.transfer')
            && $this->belongsToUserSchool($student);
    }

    public function manageGuardians(User $user, Student $student): bool
    {
        return $user->hasPermission('students.manage-guardians')
            && $this->belongsToUserSchool($student);
    }

    private function belongsToUserSchool(Student $student): bool
    {
        $activeSchool = GetSchoolModel();

        return $activeSchool !== null
            && $student->school_id === $activeSchool->id;
    }

    private function isOwnProfile(User $user, Student $student): bool
    {
        return $user->id === $student->profile?->user_id;
    }
}
