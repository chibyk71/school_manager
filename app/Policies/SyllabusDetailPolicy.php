<?php

namespace App\Policies;

use App\Models\Resource\SyllabusDetail;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SyllabusDetailPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any syllabus details.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('syllabus-details.view');
    }

    /**
     * Determine whether the user can view the syllabus detail.
     *
     * @param User $user
     * @param SyllabusDetail $syllabusDetail
     * @return bool
     */
    public function view(User $user, SyllabusDetail $syllabusDetail): bool
    {
        return $user->hasPermission('syllabus-details.view') &&
               $syllabusDetail->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can create syllabus details.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('syllabus-details.create');
    }

    /**
     * Determine whether the user can update the syllabus detail.
     *
     * @param User $user
     * @param SyllabusDetail $syllabusDetail
     * @return bool
     */
    public function update(User $user, SyllabusDetail $syllabusDetail): bool
    {
        return $user->hasPermission('syllabus-details.update') &&
               $syllabusDetail->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can delete the syllabus detail.
     *
     * @param User $user
     * @param SyllabusDetail $syllabusDetail
     * @return bool
     */
    public function delete(User $user, SyllabusDetail $syllabusDetail): bool
    {
        return $user->hasPermission('syllabus-details.delete') &&
               $syllabusDetail->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can restore the syllabus detail.
     *
     * @param User $user
     * @param SyllabusDetail $syllabusDetail
     * @return bool
     */
    public function restore(User $user, SyllabusDetail $syllabusDetail): bool
    {
        return $user->hasPermission('syllabus-details.restore') &&
               $syllabusDetail->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can permanently delete the syllabus detail.
     *
     * @param User $user
     * @param SyllabusDetail $syllabusDetail
     * @return bool
     */
    public function forceDelete(User $user, SyllabusDetail $syllabusDetail): bool
    {
        return $user->hasPermission('syllabus-details.force-delete') &&
               $syllabusDetail->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can submit a syllabus detail for approval.
     *
     * @param User $user
     * @param SyllabusDetail $syllabusDetail
     * @return bool
     */
    public function submitApproval(User $user, SyllabusDetail $syllabusDetail): bool
    {
        return $user->hasPermission('syllabus-details.submit-approval') &&
               $syllabusDetail->school_id === GetSchoolModel()->id &&
               in_array($syllabusDetail->status, ['draft', 'rejected']);
    }

    /**
     * Determine whether the user can approve a syllabus detail.
     *
     * @param User $user
     * @param SyllabusDetail $syllabusDetail
     * @return bool
     */
    public function approve(User $user, SyllabusDetail $syllabusDetail): bool
    {
        return $user->hasPermission('syllabus-details.approve') &&
               $syllabusDetail->school_id === GetSchoolModel()->id &&
               $syllabusDetail->status === 'pending_approval';
    }

    /**
     * Determine whether the user can reject a syllabus detail.
     *
     * @param User $user
     * @param SyllabusDetail $syllabusDetail
     * @return bool
     */
    public function reject(User $user, SyllabusDetail $syllabusDetail): bool
    {
        return $user->hasPermission('syllabus-details.reject') &&
               $syllabusDetail->school_id === GetSchoolModel()->id &&
               $syllabusDetail->status === 'pending_approval';
    }
}
