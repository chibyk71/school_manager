<?php

namespace App\Policies;

use App\Models\Academic\TimeTableDetail;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for TimeTableDetail model.
 */
class TimeTableDetailPolicy
{
    /**
     * Determine whether the user can view any timetable details.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('timetable-details.view');
    }

    /**
     * Determine whether the user can view a specific timetable detail.
     *
     * @param User $user
     * @param TimeTableDetail $timeTableDetail
     * @return bool
     */
    public function view(User $user, TimeTableDetail $timeTableDetail): bool
    {
        return LaratrustFacade::hasPermission('timetable-details.view') && $user->school_id === $timeTableDetail->school_id;
    }

    /**
     * Determine whether the user can create timetable details.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('timetable-details.create');
    }

    /**
     * Determine whether the user can update a specific timetable detail.
     *
     * @param User $user
     * @param TimeTableDetail $timeTableDetail
     * @return bool
     */
    public function update(User $user, TimeTableDetail $timeTableDetail): bool
    {
        return LaratrustFacade::hasPermission('timetable-details.update') && $user->school_id === $timeTableDetail->school_id;
    }

    /**
     * Determine whether the user can delete a specific timetable detail.
     *
     * @param User $user
     * @param TimeTableDetail $timeTableDetail
     * @return bool
     */
    public function delete(User $user, TimeTableDetail $timeTableDetail): bool
    {
        return LaratrustFacade::hasPermission('timetable-details.delete') && $user->school_id === $timeTableDetail->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted timetable detail.
     *
     * @param User $user
     * @param TimeTableDetail $timeTableDetail
     * @return bool
     */
    public function restore(User $user, TimeTableDetail $timeTableDetail): bool
    {
        return LaratrustFacade::hasPermission('timetable-details.restore') && $user->school_id === $timeTableDetail->school_id;
    }

    /**
     * Determine whether the user can permanently delete a timetable detail.
     *
     * @param User $user
     * @param TimeTableDetail $timeTableDetail
     * @return bool
     */
    public function forceDelete(User $user, TimeTableDetail $timeTableDetail): bool
    {
        return LaratrustFacade::hasPermission('timetable-details.force-delete') && $user->school_id === $timeTableDetail->school_id;
    }
}