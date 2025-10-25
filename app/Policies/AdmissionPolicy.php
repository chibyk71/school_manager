<?php

namespace App\Policies;

use App\Models\Student\Admission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdmissionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any admissions.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('admissions.view');
    }

    /**
     * Determine whether the user can view the admission.
     *
     * @param User $user
     * @param Admission $admission
     * @return bool
     */
    public function view(User $user, Admission $admission): bool
    {
        return $user->hasPermission('admissions.view') &&
               $admission->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can create admissions.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('admissions.create');
    }

    /**
     * Determine whether the user can update the admission.
     *
     * @param User $user
     * @param Admission $admission
     * @return bool
     */
    public function update(User $user, Admission $admission): bool
    {
        return $user->hasPermission('admissions.update') &&
               $admission->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can delete the admission.
     *
     * @param User $user
     * @param Admission $admission
     * @return bool
     */
    public function delete(User $user, Admission $admission): bool
    {
        return $user->hasPermission('admissions.delete') &&
               $admission->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can restore the admission.
     *
     * @param User $user
     * @param Admission $admission
     * @return bool
     */
    public function restore(User $user, Admission $admission): bool
    {
        return $user->hasPermission('admissions.restore') &&
               $admission->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can permanently delete the admission.
     *
     * @param User $user
     * @param Admission $admission
     * @return bool
     */
    public function forceDelete(User $user, Admission $admission): bool
    {
        return $user->hasPermission('admissions.force-delete') &&
               $admission->school_id === GetSchoolModel()->id;
    }
}
