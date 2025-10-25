<?php

namespace App\Policies;

use App\Models\Academic\ClassLevel;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for ClassLevel model.
 */
class ClassLevelPolicy
{
    /**
     * Determine whether the user can view any class levels.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('class-levels.view');
    }

    /**
     * Determine whether the user can view a specific class level.
     *
     * @param User $user
     * @param ClassLevel $classLevel
     * @return bool
     */
    public function view(User $user, ClassLevel $classLevel): bool
    {
        return LaratrustFacade::hasPermission('class-levels.view') && $user->school_id === $classLevel->school_id;
    }

    /**
     * Determine whether the user can create class levels.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('class-levels.create');
    }

    /**
     * Determine whether the user can update a specific class level.
     *
     * @param User $user
     * @param ClassLevel $classLevel
     * @return bool
     */
    public function update(User $user, ClassLevel $classLevel): bool
    {
        return LaratrustFacade::hasPermission('class-levels.update') && $user->school_id === $classLevel->school_id;
    }

    /**
     * Determine whether the user can delete a specific class level.
     *
     * @param User $user
     * @param ClassLevel $classLevel
     * @return bool
     */
    public function delete(User $user, ClassLevel $classLevel): bool
    {
        return LaratrustFacade::hasPermission('class-levels.delete') && $user->school_id === $classLevel->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted class level.
     *
     * @param User $user
     * @param ClassLevel $classLevel
     * @return bool
     */
    public function restore(User $user, ClassLevel $classLevel): bool
    {
        return LaratrustFacade::hasPermission('class-levels.restore') && $user->school_id === $classLevel->school_id;
    }

    /**
     * Determine whether the user can permanently delete a class level.
     *
     * @param User $user
     * @param ClassLevel $classLevel
     * @return bool
     */
    public function forceDelete(User $user, ClassLevel $classLevel): bool
    {
        return \Laratrust\LaratrustFacade::hasPermission('class-levels.force-delete') && $user->school_id === $classLevel->school_id;
    }
}