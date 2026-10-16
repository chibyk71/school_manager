<?php

/**
 * File Manager Phase 1 — Storage provider authorization.
 *
 * Capabilities (scope-neutral permission names):
 *   storage-providers.view   — list and view safe provider details
 *   storage-providers.manage — create, update, test within the current context
 *
 * System vs school mutation boundaries are enforced in the controller/service
 * using ownership + active school context, not by encoding scope in the
 * permission name.
 */

namespace App\Policies;

use App\Models\StorageProvider;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class StorageProviderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): Response
    {
        return $user->hasPermission('storage-providers.view')
            || $user->hasPermission('storage-providers.manage')
            ? Response::allow()
            : Response::deny('You do not have permission to view storage providers.');
    }

    public function view(User $user, StorageProvider $provider): Response
    {
        return $this->viewAny($user);
    }

    public function create(User $user): Response
    {
        return $user->hasPermission('storage-providers.manage')
            ? Response::allow()
            : Response::deny('You do not have permission to create storage providers.');
    }

    public function update(User $user, StorageProvider $provider): Response
    {
        return $user->hasPermission('storage-providers.manage')
            ? Response::allow()
            : Response::deny('You do not have permission to update storage providers.');
    }

    public function testConnection(User $user): Response
    {
        return $user->hasPermission('storage-providers.manage')
            ? Response::allow()
            : Response::deny('You do not have permission to test storage provider connections.');
    }

    public function checkDependencies(User $user, StorageProvider $provider): Response
    {
        return $this->view($user, $provider);
    }
}
