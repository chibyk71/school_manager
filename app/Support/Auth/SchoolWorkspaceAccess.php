<?php

namespace App\Support\Auth;

use App\Models\School;
use App\Models\User;

/**
 * Whether an authenticated account may use a School as an active workspace.
 *
 * Authentication remains independent of School membership.
 *
 * Current authorization rule (Phase 1):
 * - Valid participation only: the account is linked via school_users.
 *
 * Tenant-wide cross-School workspace authority is intentionally NOT granted via:
 * - schools.view-any (catalogue meaning is list access, not workspace entry)
 * - role-name checks such as super-admin (AuthorizationService forbids role-name bypass)
 *
 * Limitation reported for product/architecture: the permission catalogue has no
 * capability whose documented meaning is "operate in any School workspace".
 * Until such a capability is explicitly approved and catalogued, only school_users
 * participation authorizes workspace selection. Ordinary Tenant-scoped roles do not
 * grant unrestricted School access.
 */
final class SchoolWorkspaceAccess
{
    public static function canSelect(User $user, School $school): bool
    {
        return $user->schools()->where('schools.id', $school->id)->exists();
    }
}
