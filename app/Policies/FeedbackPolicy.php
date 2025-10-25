<?php

namespace App\Policies;

use App\Models\Communication\Feedback;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Policy for Feedback model authorization.
 */
class FeedbackPolicy
{
    /**
     * Determine whether the user can view any feedback.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('feedback.view');
    }

    /**
     * Determine whether the user can view the feedback.
     *
     * @param User $user
     * @param Feedback $feedback
     * @return bool
     */
    public function view(User $user, Feedback $feedback): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('feedback.view') && $feedback->school_id === $school?->id;
    }

    /**
     * Determine whether the user can create feedback.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('feedback.create');
    }

    /**
     * Determine whether the user can update the feedback.
     *
     * @param User $user
     * @param Feedback $feedback
     * @return bool
     */
    public function update(User $user, Feedback $feedback): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('feedback.update') && $feedback->school_id === $school?->id;
    }

    /**
     * Determine whether the user can delete the feedback.
     *
     * @param User $user
     * @param Feedback $feedback
     * @return bool
     */
    public function delete(User $user, Feedback $feedback): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('feedback.delete') && $feedback->school_id === $school?->id;
    }

    /**
     * Determine whether the user can restore a soft-deleted feedback.
     *
     * @param User $user
     * @param Feedback $feedback
     * @return bool
     */
    public function restore(User $user, Feedback $feedback): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('feedback.restore') && $feedback->school_id === $school?->id;
    }

    /**
     * Determine whether the user can permanently delete the feedback.
     *
     * @param User $user
     * @param Feedback $feedback
     * @return bool
     */
    public function forceDelete(User $user, Feedback $feedback): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('feedback.force-delete') && $feedback->school_id === $school?->id;
    }
}
