<?php

namespace App\Policies;

use App\Models\Academic\ClassSection;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for ClassSection model.
 */
class ClassSectionPolicy
{
    /**
     * Determine whether the user can view any class sections.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('class-sections.view');
    }

    /**
     * Determine whether the user can view a specific class section.
     *
     * @param User $user
     * @param ClassSection $classSection
     * @return bool
     */
    public function view(User $user, ClassSection $classSection): bool
    {
        return LaratrustFacade::hasPermission('class-sections.view') && $user->school_id === $classSection->school_id;
    }

    /**
     * Determine whether the user can create class sections.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('class-sections.create');
    }

    /**
     * Determine whether the user can update a specific class section.
     *
     * @param User $user
     * @param ClassSection $classSection
     * @return bool
     */
    public function update(User $user, ClassSection $classSection): bool
    {
        return LaratrustFacade::hasPermission('class-sections.update') && $user->school_id === $classSection->school_id;
    }

    /**
     * Determine whether the user can delete a specific class section.
     *
     * @param User $user
     * @param ClassSection $classSection
     * @return bool
     */
    public function delete(User $user, ClassSection $classSection): bool
    {
        return LaratrustFacade::hasPermission('class-sections.delete') && $user->school_id === $classSection->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted class section.
     *
     * @param User $user
     * @param ClassSection $classSection
     * @return bool
     */
    public function restore(User $user, ClassSection $classSection): bool
    {
        return LaratrustFacade::hasPermission('class-sections.restore') && $user->school_id === $classSection->school_id;
    }

    /**
     * Determine whether the user can permanently delete a class section.
     *
     * @param User $user
     * @param ClassSection $classSection
     * @return bool
     */
    public function forceDelete(User $user, ClassSection $classSection): bool
    {
        return LaratrustFacade::hasPermission('class-sections.force-delete') && $user->school_id === $classSection->school_id;
    }
}