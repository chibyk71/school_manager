<?php

namespace App\Policies;

use App\Models\Student\Admission;
use App\Models\User;

/**
 * Authorization for Admission (Phase 3).
 * Uses permission names; every decision is school-scoped.
 */
class AdmissionPolicy
{
    protected function sameSchool(Admission $admission): bool
    {
        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;

        return $school && $admission->school_id === $school->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('admissions.view');
    }

    public function view(User $user, Admission $admission): bool
    {
        return $user->hasPermission('admissions.view') && $this->sameSchool($admission);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('admissions.create');
    }

    public function issue(User $user): bool
    {
        return $user->hasPermission('admissions.issue')
            || $user->hasPermission('admissions.create');
    }

    public function direct(User $user): bool
    {
        return $user->hasPermission('admissions.direct');
    }

    public function bypass(User $user): bool
    {
        return $user->hasPermission('admissions.bypass');
    }

    public function accept(User $user, Admission $admission): bool
    {
        return ($user->hasPermission('admissions.accept') || $user->hasPermission('admissions.update'))
            && $this->sameSchool($admission);
    }

    public function decline(User $user, Admission $admission): bool
    {
        return ($user->hasPermission('admissions.decline') || $user->hasPermission('admissions.update'))
            && $this->sameSchool($admission);
    }

    public function cancel(User $user, Admission $admission): bool
    {
        return ($user->hasPermission('admissions.cancel') || $user->hasPermission('admissions.update'))
            && $this->sameSchool($admission);
    }

    public function expire(User $user, Admission $admission): bool
    {
        return ($user->hasPermission('admissions.expire') || $user->hasPermission('admissions.update'))
            && $this->sameSchool($admission);
    }

    public function manageDeadlines(User $user, Admission $admission): bool
    {
        return ($user->hasPermission('admissions.manage-deadlines') || $user->hasPermission('admissions.update'))
            && $this->sameSchool($admission);
    }

    public function update(User $user, Admission $admission): bool
    {
        return $user->hasPermission('admissions.update') && $this->sameSchool($admission);
    }

    public function delete(User $user, Admission $admission): bool
    {
        return $user->hasPermission('admissions.delete') && $this->sameSchool($admission);
    }

    public function restore(User $user, Admission $admission): bool
    {
        return $user->hasPermission('admissions.restore') && $this->sameSchool($admission);
    }

    public function forceDelete(User $user, Admission $admission): bool
    {
        return $user->hasPermission('admissions.force-delete') && $this->sameSchool($admission);
    }
}
