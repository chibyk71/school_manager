<?php

namespace App\Policies\FileManager;

use App\Models\FileManager\File;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FilePolicy
{
    public function viewAny(User $user): Response
    {
        return $user->hasPermission('file-manager.view')
            ? Response::allow()
            : Response::deny('You do not have permission to browse files.');
    }

    public function view(User $user, File $file): Response
    {
        return $user->hasPermission('file-manager.view')
            ? Response::allow()
            : Response::deny('You do not have permission to view this file.');
    }

    public function create(User $user): Response
    {
        return $user->hasPermission('file-manager.manage')
            ? Response::allow()
            : Response::deny('You do not have permission to upload files.');
    }

    public function update(User $user, File $file): Response
    {
        return $user->hasPermission('file-manager.manage')
            ? Response::allow()
            : Response::deny('You do not have permission to update files.');
    }

    public function delete(User $user, File $file): Response
    {
        return $user->hasPermission('file-manager.manage')
            ? Response::allow()
            : Response::deny('You do not have permission to delete files.');
    }

    public function download(User $user, File $file): Response
    {
        return $user->hasPermission('file-manager.view')
            ? Response::allow()
            : Response::deny('You do not have permission to download this file.');
    }

    public function attach(User $user, File $file): Response
    {
        return $user->hasPermission('file-manager.attach')
            ? Response::allow()
            : Response::deny('You do not have permission to attach files.');
    }
}
