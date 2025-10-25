<?php

namespace App\Policies;

use App\Models\Resource\Syllabus;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SyllabusPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any syllabi.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('syllabi.view');
    }

    /**
     * Determine whether the user can view the syllabus.
     *
     * @param User $user
     * @param Syllabus $syllabus
     * @return bool
     */
    public function view(User $user, Syllabus $syllabus): bool
    {
        return $user->hasPermission('syllabi.view') &&
               $syllabus->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can create syllabi.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('syllabi.create');
    }

    /**
     * Determine whether the user can update the syllabus.
     *
     * @param User $user
     * @param Syllabus $syllabus
     * @return bool
     */
    public function update(User $user, Syllabus $syllabus): bool
    {
        return $user->hasPermission('syllabi.update') &&
               $syllabus->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can delete the syllabus.
     *
     * @param User $user
     * @param Syllabus $syllabus
     * @return bool
     */
    public function delete(User $user, Syllabus $syllabus): bool
    {
        return $user->hasPermission('syllabi.delete') &&
               $syllabus->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can restore the syllabus.
     *
     * @param User $user
     * @param Syllabus $syllabus
     * @return bool
     */
    public function restore(User $user, Syllabus $syllabus): bool
    {
        return $user->hasPermission('syllabi.restore') &&
               $syllabus->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can permanently delete the syllabus.
     *
     * @param User $user
     * @param Syllabus $syllabus
     * @return bool
     */
    public function forceDelete(User $user, Syllabus $syllabus): bool
    {
        return $user->hasPermission('syllabi.force-delete') &&
               $syllabus->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can submit a syllabus for approval.
     *
     * @param User $user
     * @param Syllabus $syllabus
     * @return bool
     */
    public function submitApproval(User $user, Syllabus $syllabus): bool
    {
        return $user->hasPermission('syllabi.submit-approval') &&
               $syllabus->school_id === GetSchoolModel()->id &&
               in_array($syllabus->status, ['draft', 'rejected']);
    }

    /**
     * Determine whether the user can approve a syllabus.
     *
     * @param User $user
     * @param Syllabus $syllabus
     * @return bool
     */
    public function approve(User $user, Syllabus $syllabus): bool
    {
        return $user->hasPermission('syllabi.approve') &&
               $syllabus->school_id === GetSchoolModel()->id &&
               $syllabus->status === 'pending_approval';
    }

    /**
     * Determine whether the user can reject a syllabus.
     *
     * @param User $user
     * @param Syllabus $syllabus
     * @return bool
     */
    public function reject(User $user, Syllabus $syllabus): bool
    {
        return $user->hasPermission('syllabi.reject') &&
               $syllabus->school_id === GetSchoolModel()->id &&
               $syllabus->status === 'pending_approval';
    }
}
