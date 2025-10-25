<?php

namespace App\Policies;

use App\Models\Configuration\EventType;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Policy for EventType model authorization.
 */
class EventTypePolicy
{
    /**
     * Determine whether the user can view any event types.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('event-types.view');
    }

    /**
     * Determine whether the user can view the event type.
     *
     * @param User $user
     * @param EventType $eventType
     * @return bool
     */
    public function view(User $user, EventType $eventType): bool
    {
        return LaratrustFacade::hasPermission('event-types.view');
    }

    /**
     * Determine whether the user can create event types.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('event-types.create');
    }

    /**
     * Determine whether the user can update the event type.
     *
     * @param User $user
     * @param EventType $eventType
     * @return bool
     */
    public function update(User $user, EventType $eventType): bool
    {
        return LaratrustFacade::hasPermission('event-types.update');
    }

    /**
     * Determine whether the user can delete the event type.
     *
     * @param User $user
     * @param EventType $eventType
     * @return bool
     */
    public function delete(User $user, EventType $eventType): bool
    {
        return LaratrustFacade::hasPermission('event-types.delete');
    }

    /**
     * Determine whether the user can restore a soft-deleted event type.
     *
     * @param User $user
     * @param EventType $eventType
     * @return bool
     */
    public function restore(User $user, EventType $eventType): bool
    {
        return LaratrustFacade::hasPermission('event-types.restore');
    }

    /**
     * Determine whether the user can permanently delete the event type.
     *
     * @param User $user
     * @param EventType $eventType
     * @return bool
     */
    public function forceDelete(User $user, EventType $eventType): bool
    {
        return LaratrustFacade::hasPermission('event-types.force-delete');
    }
}
