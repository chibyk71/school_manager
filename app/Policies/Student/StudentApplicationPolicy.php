<?php

namespace App\Policies\Student;

use App\Models\Student\StudentApplication;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StudentApplicationPolicy
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
        return $user->hasPermission('applications.view');
    }

    public function view(User $user, StudentApplication $application): bool
    {
        return $user->hasPermission('applications.view')
            && $this->belongsToUserSchool($user, $application);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('applications.create');
    }

    public function review(User $user, StudentApplication $application): bool
    {
        return $user->hasPermission('applications.review')
            && $this->belongsToUserSchool($user, $application);
    }

    public function approve(User $user, StudentApplication $application): bool
    {
        return $user->hasPermission('applications.approve')
            && $this->belongsToUserSchool($user, $application);
    }

    public function reject(User $user, StudentApplication $application): bool
    {
        return $user->hasPermission('applications.reject')
            && $this->belongsToUserSchool($user, $application);
    }

    public function delete(User $user, StudentApplication $application): bool
    {
        return $user->hasPermission('applications.delete')
            && $this->belongsToUserSchool($user, $application);
    }

    public function restore(User $user, StudentApplication $application): bool
    {
        return $user->hasPermission('applications.restore')
            && $this->belongsToUserSchool($user, $application);
    }

    private function belongsToUserSchool(User $user, StudentApplication $application): bool
    {
        $activeSchool = function_exists('GetSchoolModel') ? GetSchoolModel() : null;

        return $activeSchool !== null
            && $application->school_id === $activeSchool->id;
    }
}
