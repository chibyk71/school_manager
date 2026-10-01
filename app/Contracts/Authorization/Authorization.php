<?php

/**
 * Permission Phase 4 — canonical application authorization boundary.
 *
 * Application code asks capability questions here instead of talking to
 * Laratrust teams, pivots, or checkers directly.
 *
 * Organizational eligibility (may the user operate against this school?)
 * remains outside this boundary. Domain/resource rules remain in Policies.
 */

namespace App\Contracts\Authorization;

use App\Models\User;

interface Authorization
{
    /**
     * Whether the user possesses the given permission capability.
     *
     * When $schoolId is null, evaluation uses the current application
     * authorization context (active school via schoolManager, or tenant
     * when no school is active).
     *
     * When $schoolId is provided, evaluation targets that school only and
     * MUST NOT mutate session, SchoolContext, or the active school.
     */
    public function allows(User $user, string $permission, ?string $schoolId = null): bool;

    /**
     * Inverse of allows().
     */
    public function denies(User $user, string $permission, ?string $schoolId = null): bool;
}
