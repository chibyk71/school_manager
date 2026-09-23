<?php

/**
 * Dynamic Enum Phase 4 — authorization.
 *
 * Permissions:
 *   dynamic-enums.view
 *   dynamic-enums.manage          — school configuration
 *   dynamic-enums.manageGlobals   — tenant/default configuration
 *
 * School scope is enforced by the administration layer (resolved school),
 * not by trusting request school_id.
 */

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class DynamicEnumPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): Response
    {
        return $user->hasPermission('dynamic-enums.view')
            || $user->hasPermission('dynamic-enums.manage')
            || $user->hasPermission('dynamic-enums.manageGlobals')
            ? Response::allow()
            : Response::deny('You do not have permission to view Dynamic Enums.');
    }

    public function view(User $user): Response
    {
        return $this->viewAny($user);
    }

    public function manage(User $user): Response
    {
        return $user->hasPermission('dynamic-enums.manage')
            ? Response::allow()
            : Response::deny('You do not have permission to manage school Dynamic Enum configuration.');
    }

    public function manageGlobals(User $user): Response
    {
        return $user->hasPermission('dynamic-enums.manageGlobals')
            ? Response::allow()
            : Response::deny('You do not have permission to manage tenant/default Dynamic Enum configuration.');
    }
}
