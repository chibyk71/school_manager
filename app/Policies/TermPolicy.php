<?php

namespace App\Policies;

use App\Models\Academic\Term;
use App\Models\User;
use Laratrust\LaratrustFacade;

/**
 * Authorization policy for Term model.
 */
class TermPolicy
{
    /**
     * Determine whether the user can view any terms.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return LaratrustFacade::hasPermission('terms.view');
    }

    /**
     * Determine whether the user can view a specific term.
     *
     * @param User $user
     * @param Term $term
     * @return bool
     */
    public function view(User $user, Term $term): bool
    {
        return LaratrustFacade::hasPermission('terms.view') && $user->school_id === $term->school_id;
    }

    /**
     * Determine whether the user can create terms.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return LaratrustFacade::hasPermission('terms.create');
    }

    /**
     * Determine whether the user can update a specific term.
     *
     * @param User $user
     * @param Term $term
     * @return bool
     */
    public function update(User $user, Term $term): bool
    {
        return LaratrustFacade::hasPermission('terms.update') && $user->school_id === $term->school_id;
    }

    /**
     * Determine whether the user can delete a specific term.
     *
     * @param User $user
     * @param Term $term
     * @return bool
     */
    public function delete(User $user, Term $term): bool
    {
        return LaratrustFacade::hasPermission('terms.delete') && $user->school_id === $term->school_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted term.
     *
     * @param User $user
     * @param Term $term
     * @return bool
     */
    public function restore(User $user, Term $term): bool
    {
        return LaratrustFacade::hasPermission('terms.restore') && $user->school_id === $term->school_id;
    }

    /**
     * Determine whether the user can permanently delete a term.
     *
     * @param User $user
     * @param Term $term
     * @return bool
     */
    public function forceDelete(User $user, Term $term): bool
    {
        return LaratrustFacade::hasPermission('terms.force-delete') && $user->school_id === $term->school_id;
    }
}