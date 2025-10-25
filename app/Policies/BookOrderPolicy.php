<?php

namespace App\Policies;

use App\Models\Resource\BookOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BookOrderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any book orders.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('book-orders.view');
    }

    /**
     * Determine whether the user can view the book order.
     *
     * @param User $user
     * @param BookOrder $bookOrder
     * @return bool
     */
    public function view(User $user, BookOrder $bookOrder): bool
    {
        return $user->hasPermission('book-orders.view') &&
               $bookOrder->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can create book orders.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('book-orders.create');
    }

    /**
     * Determine whether the user can update the book order.
     *
     * @param User $user
     * @param BookOrder $bookOrder
     * @return bool
     */
    public function update(User $user, BookOrder $bookOrder): bool
    {
        return $user->hasPermission('book-orders.update') &&
               $bookOrder->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can delete the book order.
     *
     * @param User $user
     * @param BookOrder $bookOrder
     * @return bool
     */
    public function delete(User $user, BookOrder $bookOrder): bool
    {
        return $user->hasPermission('book-orders.delete') &&
               $bookOrder->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can restore the book order.
     *
     * @param User $user
     * @param BookOrder $bookOrder
     * @return bool
     */
    public function restore(User $user, BookOrder $bookOrder): bool
    {
        return $user->hasPermission('book-orders.restore') &&
               $bookOrder->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can permanently delete the book order.
     *
     * @param User $user
     * @param BookOrder $bookOrder
     * @return bool
     */
    public function forceDelete(User $user, BookOrder $bookOrder): bool
    {
        return $user->hasPermission('book-orders.force-delete') &&
               $bookOrder->school_id === GetSchoolModel()->id;
    }
}
