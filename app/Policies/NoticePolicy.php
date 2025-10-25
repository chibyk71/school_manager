<?php

namespace App\Policies;

use App\Models\Communication\Notice;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Policy for Notice model authorization.
 */
class NoticePolicy
{
    /**
     * Determine whether the user can view any notices.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('notices.view');
    }

    /**
     * Determine whether the user can view the notice.
     *
     * @param User $user
     * @param Notice $notice
     * @return bool
     */
    public function view(User $user, Notice $notice): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('notices.view') &&
               ($notice->is_public || $notice->school_id === $school?->id);
    }

    /**
     * Determine whether the user can create notices.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('notices.create');
    }

    /**
     * Determine whether the user can update the notice.
     *
     * @param User $user
     * @param Notice $notice
     * @return bool
     */
    public function update(User $user, Notice $notice): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('notices.update') && $notice->school_id === $school?->id;
    }

    /**
     * Determine whether the user can delete the notice.
     *
     * @param User $user
     * @param Notice $notice
     * @return bool
     */
    public function delete(User $user, Notice $notice): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('notices.delete') && $notice->school_id === $school?->id;
    }

    /**
     * Determine whether the user can restore a soft-deleted notice.
     *
     * @param User $user
     * @param Notice $notice
     * @return bool
     */
    public function restore(User $user, Notice $notice): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('notices.restore') && $notice->school_id === $school?->id;
    }

    /**
     * Determine whether the user can permanently delete the notice.
     *
     * @param User $user
     * @param Notice $notice
     * @return bool
     */
    public function forceDelete(User $user, Notice $notice): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('notices.force-delete') && $notice->school_id === $school?->id;
    }

    /**
     * Determine whether the user can mark the notice as read.
     *
     * @param User $user
     * @param Notice $notice
     * @return bool
     */
    public function markRead(User $user, Notice $notice): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('notices.mark-read') &&
               ($notice->is_public || $notice->school_id === $school?->id) &&
               $notice->recipients()->where('user_id', $user->id)->exists();
    }
}
