<?php

namespace App\Policies;

use App\Models\Academic\TimeTable;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for TimeTable model.
 */
class TimeTablePolicy
{
    /**
     * Determine whether the user can view any timetables.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('timetables.view');
    }

    /**
     * Determine whether the user can view a specific timetable.
     *
     * @param User $user
     * @param TimeTable $timeTable
     * @return bool
     */
    public function view(User $user, TimeTable $timeTable): bool
    {
        return LaratrustFacade::hasPermission('timetables.view') && $user->school_id === $timeTable->school_id;
    }

    /**
     * Determine whether the user can create timetables.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('timetables.create');
    }

    /**
     * Determine whether the user can update a specific timetable.
     *
     * @param User $user
     * @param TimeTable $timeTable
     * @return bool
     */
    public function update(User $user, TimeTable $timeTable): bool
    {
        return LaratrustFacade::hasPermission('timetables.update') && $user->school_id === $timeTable->school_id;
    }

    /**
     * Determine whether the user can delete a specific timetable.
     *
     * @param User $user
     * @param TimeTable $timeTable
     * @return bool
     */
    public function delete(User $user, TimeTable $timeTable): bool
    {
        return LaratrustFacade::hasPermission('timetables.delete') && $user->school_id === $timeTable->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted timetable.
     *
     * @param User $user
     * @param TimeTable $timeTable
     * @return bool
     */
    public function restore(User $user, TimeTable $timeTable): bool
    {
        return LaratrustFacade::hasPermission('timetables.restore') && $user->school_id === $timeTable->school_id;
    }

    /**
     * Determine whether the user can permanently delete a timetable.
     *
     * @param User $user
     * @param TimeTable $timeTable
     * @return bool
     */
    public function forceDelete(User $user, TimeTable $timeTable): bool
    {
        return LaratrustFacade::hasPermission('timetables.force-delete') && $user->school_id === $timeTable->school_id;
    }
}