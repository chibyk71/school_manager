<?php

namespace App\Policies\Student;

use App\Models\Student\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAbleTo('enrollments.view');
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return $user->isAbleTo('enrollments.view');
    }

    public function create(User $user): bool
    {
        return $user->isAbleTo('enrollments.create');
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        if (! $enrollment->isIncomplete()) {
            return false;
        }

        return $user->isAbleTo('enrollments.edit');
    }

    public function manageRequirements(User $user, Enrollment $enrollment): bool
    {
        if (! $enrollment->isIncomplete()) {
            return false;
        }

        return $user->isAbleTo('enrollments.manage_requirements');
    }

    public function finalize(User $user, Enrollment $enrollment): bool
    {
        if (! $enrollment->isFinalizable()) {
            return false;
        }

        return $user->isAbleTo('enrollments.finalize');
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        if ($enrollment->isActive()) {
            return false;
        }

        return $user->isAbleTo('enrollments.delete');
    }
}
