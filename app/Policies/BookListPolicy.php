<?php

namespace App\Policies;

use App\Models\Resource\BookList;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BookListPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any book lists.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('book-lists.view');
    }

    /**
     * Determine whether the user can view the book list.
     *
     * @param User $user
     * @param BookList $bookList
     * @return bool
     */
    public function view(User $user, BookList $bookList): bool
    {
        return $user->hasPermission('book-lists.view') &&
               $bookList->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can create book lists.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('book-lists.create');
    }

    /**
     * Determine whether the user can update the book list.
     *
     * @param User $user
     * @param BookList $bookList
     * @return bool
     */
    public function update(User $user, BookList $bookList): bool
    {
        return $user->hasPermission('book-lists.update') &&
               $bookList->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can delete the book list.
     *
     * @param User $user
     * @param BookList $bookList
     * @return bool
     */
    public function delete(User $user, BookList $bookList): bool
    {
        return $user->hasPermission('book-lists.delete') &&
               $bookList->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can restore the book list.
     *
     * @param User $user
     * @param BookList $bookList
     * @return bool
     */
    public function restore(User $user, BookList $bookList): bool
    {
        return $user->hasPermission('book-lists.restore') &&
               $bookList->school_id === GetSchoolModel()->id;
    }

    /**
     * Determine whether the user can permanently delete the book list.
     *
     * @param User $user
     * @param BookList $bookList
     * @return bool
     */
    public function forceDelete(User $user, BookList $bookList): bool
    {
        return $user->hasPermission('book-lists.force-delete') &&
               $bookList->school_id === GetSchoolModel()->id;
    }
}