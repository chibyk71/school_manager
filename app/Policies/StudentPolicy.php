<?php

namespace App\Policies;

use App\Models\Academic\Student;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

/**
 * Authorization policy for Student model.
 *
 * Works with multi-role users and multi-school tenancy via Profile relationship.
 */
class StudentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any students.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('student.view-any');
    }

    /**
     * Determine whether the user can view a specific student.
     */
    public function view(User $user, Student $student): Response
    {
        if (! $user->can('student.view')) {
            return $this->deny('You do not have permission to view students.');
        }

        // Must belong to the same school as the student
        $hasAccess = $user->profiles()
            ->where('school_id', $student->school_id)
            ->exists();

        return $hasAccess
            ? $this->allow()
            : $this->deny('You can only view students from your school.');
    }

    /**
     * Determine whether the user can create students.
     */
    public function create(User $user): bool
    {
        return $user->can('student.create');
    }

    /**
     * Determine whether the user can update a specific student.
     */
    public function update(User $user, Student $student): Response
    {
        if (! $user->can('student.update')) {
            return $this->deny('You do not have permission to update students.');
        }

        $hasAccess = $user->profiles()
            ->where('school_id', $student->school_id)
            ->exists();

        return $hasAccess
            ? $this->allow()
            : $this->deny('You can only update students from your school.');
    }

    /**
     * Determine whether the user can delete a specific student.
     */
    public function delete(User $user, Student $student): Response
    {
        if (! $user->can('student.delete')) {
            return $this->deny('You do not have permission to delete students.');
        }

        $hasAccess = $user->profiles()
            ->where('school_id', $student->school_id)
            ->exists();

        return $hasAccess
            ? $this->allow()
            : $this->deny('You can only delete students from your school.');
    }

    /**
     * Determine whether the user can restore a soft-deleted student.
     */
    public function restore(User $user, Student $student): Response
    {
        if (! $user->can('student.restore')) {
            return $this->deny('You do not have permission to restore students.');
        }

        $hasAccess = $user->profiles()
            ->where('school_id', $student->school_id)
            ->exists();

        return $hasAccess
            ? $this->allow()
            : $this->deny('You can only restore students from your school.');
    }

    /**
     * Determine whether the user can permanently delete a student.
     */
    public function forceDelete(User $user, Student $student): Response
    {
        if (! $user->can('student.force-delete')) {
            return $this->deny('You do not have permission to permanently delete students.');
        }

        $hasAccess = $user->profiles()
            ->where('school_id', $student->school_id)
            ->exists();

        return $hasAccess
            ? $this->allow()
            : $this->deny('You can only permanently delete students from your school.');
    }
}
