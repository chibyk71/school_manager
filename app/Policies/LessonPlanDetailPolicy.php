<?php

namespace App\Policies;

use App\Models\Resource\LessonPlanDetail;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LessonPlanDetailPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any lesson plan details.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('lesson-plan-details.view');
    }

    /**
     * Determine whether the user can view the lesson plan detail.
     *
     * @param User $user
     * @param LessonPlanDetail $lessonPlanDetail
     * @return bool
     */
    public function view(User $user, LessonPlanDetail $lessonPlanDetail): bool
    {
        return $user->hasPermission('lesson-plan-details.view') &&
               $lessonPlanDetail->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can create lesson plan details.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('lesson-plan-details.create');
    }

    /**
     * Determine whether the user can update the lesson plan detail.
     *
     * @param User $user
     * @param LessonPlanDetail $lessonPlanDetail
     * @return bool
     */
    public function update(User $user, LessonPlanDetail $lessonPlanDetail): bool
    {
        return $user->hasPermission('lesson-plan-details.update') &&
               $lessonPlanDetail->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can delete the lesson plan detail.
     *
     * @param User $user
     * @param LessonPlanDetail $lessonPlanDetail
     * @return bool
     */
    public function delete(User $user, LessonPlanDetail $lessonPlanDetail): bool
    {
        return $user->hasPermission('lesson-plan-details.delete') &&
               $lessonPlanDetail->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can restore the lesson plan detail.
     *
     * @param User $user
     * @param LessonPlanDetail $lessonPlanDetail
     * @return bool
     */
    public function restore(User $user, LessonPlanDetail $lessonPlanDetail): bool
    {
        return $user->hasPermission('lesson-plan-details.restore') &&
               $lessonPlanDetail->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can permanently delete the lesson plan detail.
     *
     * @param User $user
     * @param LessonPlanDetail $lessonPlanDetail
     * @return bool
     */
    public function forceDelete(User $user, LessonPlanDetail $lessonPlanDetail): bool
    {
        return $user->hasPermission('lesson-plan-details.force-delete') &&
               $lessonPlanDetail->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can submit a lesson plan detail for approval.
     *
     * @param User $user
     * @param LessonPlanDetail $lessonPlanDetail
     * @return bool
     */
    public function submitApproval(User $user, LessonPlanDetail $lessonPlanDetail): bool
    {
        return $user->hasPermission('lesson-plan-details.submit-approval') &&
               $lessonPlanDetail->school_id === GetSchoolModel()->id &&
               in_array($lessonPlanDetail->status, ['draft', 'rejected']);
    }

    /**
     * Determine whether the user can approve a lesson plan detail.
     *
     * @param User $user
     * @param LessonPlanDetail $lessonPlanDetail
     * @return bool
     */
    public function approve(User $user, LessonPlanDetail $lessonPlanDetail): bool
    {
        return $user->hasPermission('lesson-plan-details.approve') &&
               $lessonPlanDetail->school_id === GetSchoolModel()->id &&
               $lessonPlanDetail->status === 'pending_approval';
    }

    /**
     * Determine whether the user can reject a lesson plan detail.
     *
     * @param User $user
     * @param LessonPlanDetail $lessonPlanDetail
     * @return bool
     */
    public function reject(User $user, LessonPlanDetail $lessonPlanDetail): bool
    {
        return $user->hasPermission('lesson-plan-details.reject') &&
               $lessonPlanDetail->school_id === GetSchoolModel()->id &&
               $lessonPlanDetail->status === 'pending_approval';
    }
}
