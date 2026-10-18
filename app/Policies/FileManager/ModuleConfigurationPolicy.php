<?php

namespace App\Policies\FileManager;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class ModuleConfigurationPolicy
{
    public function viewAny(User $user): Response
    {
        return $user->hasPermission('file-manager.configure')
            ? Response::allow()
            : Response::deny('You do not have permission to view File Manager module configuration.');
    }

    public function update(User $user): Response
    {
        return $user->hasPermission('file-manager.configure')
            ? Response::allow()
            : Response::deny('You do not have permission to configure File Manager modules.');
    }
}
