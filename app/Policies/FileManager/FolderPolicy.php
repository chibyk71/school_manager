<?php

namespace App\Policies\FileManager;

use App\Models\FileManager\Folder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Permission gates for folders. Ownership isolation is enforced in FolderService.
 */
class FolderPolicy
{
    public function viewAny(User $user): Response
    {
        return $user->hasPermission('file-manager.view')
            ? Response::allow()
            : Response::deny('You do not have permission to browse files and folders.');
    }

    public function view(User $user, Folder $folder): Response
    {
        return $user->hasPermission('file-manager.view')
            ? Response::allow()
            : Response::deny('You do not have permission to view this folder.');
    }

    public function create(User $user): Response
    {
        return $user->hasPermission('file-manager.manage')
            ? Response::allow()
            : Response::deny('You do not have permission to create folders.');
    }

    public function update(User $user, Folder $folder): Response
    {
        return $user->hasPermission('file-manager.manage')
            ? Response::allow()
            : Response::deny('You do not have permission to update folders.');
    }

    public function delete(User $user, Folder $folder): Response
    {
        return $user->hasPermission('file-manager.manage')
            ? Response::allow()
            : Response::deny('You do not have permission to delete folders.');
    }
}
