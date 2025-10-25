<?php

namespace App\Policies;

use App\Models\Calendar\Event;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Policy for Event model authorization.
 */
class EventPolicy
{
    /**
     * Determine whether the user can view any events.
     *
     * @param  User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('events.view');
    }

    /**
     * Determine whether the user can view the event.
     *
     * @param  User  $user
     * @param  Event  $event
     * @return bool
     */
    public function view(User $user, Event $event): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('events.view') && $event->term->school_id === $school?->id;
    }

    /**
     * Determine whether the user can create events.
     *
     * @param  User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('events.create');
    }

    /**
     * Determine whether the user can update the event.
     *
     * @param  User  $user
     * @param  Event  $event
     * @return bool
     */
    public function update(User $user, Event $event): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('events.update') && $event->term->school_id === $school?->id;
    }

    /**
     * Determine whether the user can delete the event.
     *
     * @param  User  $user
     * @param  Event  $event
     * @return bool
     */
    public function delete(User $user, Event $event): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('events.delete') && $event->term->school_id === $school?->id;
    }

    /**
     * Determine whether the user can restore a soft-deleted event.
     *
     * @param  User  $user
     * @param  Event  $event
     * @return bool
     */
    public function restore(User $user, Event $event): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('events.restore') && $event->term->school_id === $school?->id;
    }

    /**
     * Determine whether the user can permanently delete the event.
     *
     * @param  User  $user
     * @param  Event  $event
     * @return bool
     */
    public function forceDelete(User $user, Event $event): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('events.force-delete') && $event->term->school_id === $school?->id;
    }
}
