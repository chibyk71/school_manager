<?php

/**
 * Dynamic Enum Phase 7 — authorization.
 *
 * Capabilities (scope-neutral):
 *   dynamic-enums.view   — view definitions and effective configuration
 *   dynamic-enums.manage — mutate configuration in the current authorization context
 *
 * Scope is not encoded in the permission name. The active application context
 * (tenant vs school via GetSchoolModel / schoolManager) determines whether a
 * manage capability applies to tenant baseline or school overlay operations.
 * Controllers enforce that separation; this policy only answers capability.
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
            ? Response::allow()
            : Response::deny('You do not have permission to view Dynamic Enums.');
    }

    public function view(User $user): Response
    {
        return $this->viewAny($user);
    }

    /**
     * Capability to mutate Dynamic Enum configuration in the current context.
     * Tenant vs school target is decided by the controller from application context.
     */
    public function manage(User $user): Response
    {
        return $user->hasPermission('dynamic-enums.manage')
            ? Response::allow()
            : Response::deny('You do not have permission to manage Dynamic Enum configuration.');
    }
}
