<?php

namespace App\Support\Auth;

use App\Models\School;
use App\Models\User;
use App\Services\Permission\AuthorizationService;

/**
 * Whether an authenticated account may use a School as an active workspace.
 *
 * Authentication remains independent of School membership. Selecting a School
 * workspace requires either:
 * - valid participation (school_users), or
 * - explicit Tenant-wide authority (schools.view-any at tenant scope, or super-admin).
 *
 * An ordinary Tenant-scoped role does not by itself grant unrestricted School access.
 */
final class SchoolWorkspaceAccess
{
    public static function canSelect(User $user, School $school): bool
    {
        if ($user->schools()->where('schools.id', $school->id)->exists()) {
            return true;
        }

        try {
            if (app(AuthorizationService::class)->allows($user, 'schools.view-any', null)) {
                return true;
            }
        } catch (\Throwable) {
            // Authorization stack may be unavailable in minimal contexts.
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
            return true;
        }

        return false;
    }
}
