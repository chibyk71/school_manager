<?php

namespace App\Policies;

use App\Models\Resource\LessonPlan;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LessonPlanPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any lesson plans.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('lesson-plans.view');
    }

    /**
     * Determine whether the user can view the lesson plan.
     *
     * @param User $user
     * @param LessonPlan $lessonPlan
     * @return bool
     */
    public function view(User $user, LessonPlan $lessonPlan): bool
    {
        return $user->hasPermission('lesson-plans.view') &&
               $lessonPlan->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can create lesson plans.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('lesson-plans.create');
    }

    /**
     * Determine whether the user can update the lesson plan.
     *
     * @param User $user
     * @param LessonPlan $lessonPlan
     * @return bool
     */
    public function update(User $user, LessonPlan $lessonPlan): bool
    {
        return $user->hasPermission('lesson-plans.update') &&
               $lessonPlan->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can delete the lesson plan.
     *
     * @param User $user
     * @param LessonPlan $lessonPlan
     * @return bool
     */
    public function delete(User $user, LessonPlan $lessonPlan): bool
    {
        return $user->hasPermission('lesson-plans.delete') &&
               $lessonPlan->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can restore the lesson plan.
     *
     * @param User $user
     * @param LessonPlan $lessonPlan
     * @return bool
     */
    public function restore(User $user, LessonPlan $lessonPlan): bool
    {
        return $user->hasPermission('lesson-plans.restore') &&
               $lessonPlan->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can permanently delete the lesson plan.
     *
     * @param User $user
     * @param LessonPlan $lessonPlan
     * @return bool
     */
    public function forceDelete(User $user, LessonPlan $lessonPlan): bool
    {
        return $user->hasPermission('lesson-plans.force-delete') &&
               $lessonPlan->school_id === GetSchoolModel()->id;
    }
}
