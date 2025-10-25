<?php

namespace App\Policies;

use App\Models\Resource\AssignmentSubmission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssignmentSubmissionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any assignment submissions.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('assignment-submissions.view');
    }

    /**
     * Determine whether the user can view the assignment submission.
     *
     * @param User $user
     * @param AssignmentSubmission $assignmentSubmission
     * @return bool
     */
    public function view(User $user, AssignmentSubmission $assignmentSubmission): bool
    {
        return $user->hasPermission('assignment-submissions.view') &&
               $assignmentSubmission->assignment->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can create assignment submissions.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('assignment-submissions.create');
    }

    /**
     * Determine whether the user can update the assignment submission.
     *
     * @param User $user
     * @param AssignmentSubmission $assignmentSubmission
     * @return bool
     */
    public function update(User $user, AssignmentSubmission $assignmentSubmission): bool
    {
        return $user->hasPermission('assignment-submissions.update') &&
               $assignmentSubmission->assignment->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can delete the assignment submission.
     *
     * @param User $user
     * @param AssignmentSubmission $assignmentSubmission
     * @return bool
     */
    public function delete(User $user, AssignmentSubmission $assignmentSubmission): bool
    {
        return $user->hasPermission('assignment-submissions.delete') &&
               $assignmentSubmission->assignment->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can restore the assignment submission.
     *
     * @param User $user
     * @param AssignmentSubmission $assignmentSubmission
     * @return bool
     */
    public function restore(User $user, AssignmentSubmission $assignmentSubmission): bool
    {
        return $user->hasPermission('assignment-submissions.restore') &&
               $assignmentSubmission->assignment->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can permanently delete the assignment submission.
     *
     * @param User $user
     * @param AssignmentSubmission $assignmentSubmission
     * @return bool
     */
    public function forceDelete(User $user, AssignmentSubmission $assignmentSubmission): bool
    {
        return $user->hasPermission('assignment-submissions.force-delete') &&
               $assignmentSubmission->assignment->school_id === GetSchoolModel()->id;
    }
}