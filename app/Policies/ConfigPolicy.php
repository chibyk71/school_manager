<?php

namespace App\Policies;

use App\Models\Configuration\Config;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Policy for Config model authorization.
 */
class ConfigPolicy
{
    /**
     * Determine whether the user can view any configurations.
     *
     * @param  User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('configs.view');
    }

    /**
     * Determine whether the user can view the configuration.
     *
     * @param  User  $user
     * @param  Config  $config
     * @return bool
     */
    public function view(User $user, Config $config): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('configs.view') &&
               ($config->scope_type === null || ($config->scope_type === \App\Models\School::class && $config->scope_id === $school?->id));
    }

    /**
     * Determine whether the user can create configurations.
     *
     * @param  User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('configs.create');
    }

    /**
     * Determine whether the user can update the configuration.
     *
     * @param  User  $user
     * @param  Config  $config
     * @return bool
     */
    public function update(User $user, Config $config): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('configs.update') &&
               ($config->scope_type === null || ($config->scope_type === \App\Models\School::class && $config->scope_id === $school?->id));
    }

    /**
     * Determine whether the user can delete the configuration.
     *
     * @param  User  $user
     * @param  Config  $config
     * @return bool
     */
    public function delete(User $user, Config $config): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('configs.delete') &&
               ($config->scope_type === null || ($config->scope_type === \App\Models\School::class && $config->scope_id === $school?->id));
    }

    /**
     * Determine whether the user can restore a soft-deleted configuration.
     *
     * @param  User  $user
     * @param  Config  $config
     * @return bool
     */
    public function restore(User $user, Config $config): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('configs.restore') &&
               ($config->scope_type === null || ($config->scope_type === \App\Models\School::class && $config->scope_id === $school?->id));
    }

    /**
     * Determine whether the user can permanently delete the configuration.
     *
     * @param  User  $user
     * @param  Config  $config
     * @return bool
     */
    public function forceDelete(User $user, Config $config): bool
    {
        $school = GetSchoolModel();
        return LaratrustFacade::hasPermission('configs.force-delete') &&
               ($config->scope_type === null || ($config->scope_type === \App\Models\School::class && $config->scope_id === $school?->id));
    }
}
