<?php

namespace App\Policies;

use App\Models\Transport\Route;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Policy for Route model authorization.
 */
class RoutePolicy
{
    /**
     * Determine whether the user can view any routes.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('routes.view');
    }

    /**
     * Determine whether the user can view the route.
     *
     * @param User $user
     * @param Route $route
     * @return bool
     */
    public function view(User $user, Route $route): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('routes.view') && $route->school_id === $school?->id;
    }

    /**
     * Determine whether the user can create routes.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('routes.create');
    }

    /**
     * Determine whether the user can update the route.
     *
     * @param User $user
     * @param Route $route
     * @return bool
     */
    public function update(User $user, Route $route): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('routes.update') && $route->school_id === $school?->id;
    }

    /**
     * Determine whether the user can delete the route.
     *
     * @param User $user
     * @param Route $route
     * @return bool
     */
    public function delete(User $user, Route $route): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('routes.delete') && $route->school_id === $school?->id;
    }

    /**
     * Determine whether the user can restore a soft-deleted route.
     *
     * @param User $user
     * @param Route $route
     * @return bool
     */
    public function restore(User $user, Route $route): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('routes.restore') && $route->school_id === $school?->id;
    }

    /**
     * Determine whether the user can permanently delete the route.
     *
     * @param User $user
     * @param Route $route
     * @return bool
     */
    public function forceDelete(User $user, Route $route): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('routes.force-delete') && $route->school_id === $school?->id;
    }
}
