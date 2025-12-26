<?php

namespace App\Checkers;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Laratrust\Checkers\User\UserDefaultChecker;
use Laratrust\Contracts\LaratrustUserInterface;
use BackedEnum;
use Laratrust\Contracts\Role;

/**
 * CustomUserChecker
 *
 * Purpose:
 * --------
 * This class extends Laratrust's default user checker to implement custom scoping behavior
 * for roles and permissions in a multi-section school SaaS application.
 *
 * Problem We Are Solving:
 * -----------------------
 * In our system:
 * - Roles and permissions can be assigned in two ways:
 *   1. Scoped to a specific section (team) → e.g., "teacher" only in Primary section
 *   2. Globally (no team) → e.g., "sport-director", "bursar" applies school-wide
 *
 * Desired Check Behavior:
 * ----------------------
 * When checking a role/permission with a specific team (current active section):
 * - First: Look for an exact match in that section (strict scoped check)
 * - If not found: Fall back to global (team_id = null) assignment
 * - This allows "school-wide" roles to work in any section without duplicating assignments
 *
 * Example:
 * - User has "sport-director" assigned globally (no team)
 * - Current section = Primary
 * - $user->hasRole('sport-director', $primarySection) → should return TRUE
 * - But $user->hasRole('teacher', $primarySection) → only TRUE if explicitly assigned to Primary
 *
 * Why Use a Custom Checker?
 * ------------------------
 * - Cleaner than overriding methods on the User model
 * - Keeps all authorization logic in one place
 * - Preserves Laratrust's caching mechanism (since we extend UserDefaultChecker)
 * - Enforces consistent behavior across the entire application
 * - No need to remember to use custom helpers — all $user->hasRole() / $user->can() calls automatically use this logic
 *
 * Important Notes:
 * ---------------
 * - We extend UserDefaultChecker to retain full caching support
 * - We will override key methods: currentUserHasRole() and currentUserHasPermission()
 * - We may also override getCurrentUserRoles() if needed for listing
 * - System-admin role should bypass all checks (full access)
 *
 * Configuration Requirement:
 * -------------------------
 * In config/laratrust.php:
 * 'checkers' => [
 *     'user' => App\Checkers\CustomUserChecker::class,
 *     'role' => 'default',
 * ],
 *
 * After implementing this, clear config cache: php artisan config:clear
 */
class CustomUserChecker extends UserDefaultChecker
{
    /**
     * Get all role names the user currently has, optionally filtered by team.
     *
     * Custom Behavior Implemented:
     * ---------------------------
     * In our school SaaS application, we want role listing to reflect the same flexible scoping
     * logic used in hasRole() checks:
     *
     * - When a specific team (section) is provided:
     *   • First: Return roles explicitly assigned to that section (scoped roles)
     *   • Then: Also include any global roles (team_id = null) because they apply school-wide
     *   • This ensures that when viewing "roles in current section", school-wide roles (e.g., sport-director, bursar)
     *     appear alongside section-specific ones (e.g., teacher in Primary)
     *
     * - When no team is provided (null):
     *   • Return all unique role names the user has, regardless of scoping
     *   • This matches standard Laratrust behavior and is useful for global user summaries
     *
     * Why This Matters:
     * -----------------
     * - Consistency: The roles listed in a section context should match what hasRole(..., $section) would return true for
     * - UX: Admins viewing a user's roles in the Primary section should see both "teacher" (scoped) and "sport-director" (global)
     * - Prevents confusion: Without global fallback, school-wide roles would disappear when viewing per-section
     *
     * Example:
     * - User has:
     *   → "teacher" assigned to Primary section (team_id = 1)
     *   → "sport-director" assigned globally (team_id = null)
     *
     * $checker->getCurrentUserRoles($primarySection)
     * → Should return ['teacher', 'sport-director']
     *
     * $checker->getCurrentUserRoles($secondarySection)
     * → Should return ['sport-director']  (only global)
     *
     * @param mixed $team  Can be null, Team model instance, section model instance, or ID
     * @return array       Array of role names (strings)
     */
    public function getCurrentUserRoles(mixed $team = null): array
    {
        // Resolve the team ID if a team is provided (model or ID)
        $teamId = $team ? \Laratrust\Helper::getIdFor($team, 'team') : null;

        // Get all cached roles with their pivot data (including team_id)
        // This uses the parent's caching mechanism — efficient and consistent
        $cachedRoles = collect($this->userCachedRoles());

        $roleNames = collect();

        if ($teamId !== null) {
            // === Case 1: Specific team (section) requested ===

            // 1. Add roles explicitly scoped to this team
            $scopedRoles = $cachedRoles->filter(function ($role) use ($teamId) {
                return $role['pivot'][\Laratrust\Models\Team::modelForeignKey()] == $teamId;
            });

            $roleNames = $roleNames->merge($scopedRoles->pluck('name'));

            // 2. Add global roles (team_id = null) — they apply everywhere
            $globalRoles = $cachedRoles->filter(function ($role) {
                return $role['pivot'][\Laratrust\Models\Team::modelForeignKey()] === null;
            });

            $roleNames = $roleNames->merge($globalRoles->pluck('name'));
        } else {
            // === Case 2: No team specified → return all unique role names ===

            // Standard behavior: all roles the user has, scoped or global
            // We use pluck to get names, then unique() to avoid duplicates
            $roleNames = $cachedRoles->pluck('name');
        }

        // Return unique role names as plain array
        // Ensures no duplicates if a role is somehow assigned both globally and scoped
        return $roleNames->unique()->values()->toArray();
    }

    /**
     * Check if the current user has the given role(s).
     *
     * Custom Scoping Behavior Implemented:
     * -----------------------------------
     * This method overrides Laratrust's default role checking to support our school's
     * flexible role assignment model:
     *
     * - Roles can be assigned:
     *   • Scoped: explicitly to a specific section (team) → e.g., "teacher" only in Primary
     *   • Global: without a team (team_id = null) → e.g., "sport-director", "bursar" applies school-wide
     *
     * Desired Check Logic (when a team/section is provided):
     * ------------------------------------------------------
     * 1. If a specific team (current active section) is passed:
     *    - First: Check if the role is explicitly assigned to that section (strict scoped match)
     *    - If not found: Fall back to checking if the role is assigned globally (team_id = null)
     *    - This allows school-wide roles to be valid in any section context
     *
     * 2. If no team is passed (null):
     *    - Use standard Laratrust behavior: check if the role exists anywhere (scoped or global)
     *
     * 3. Special Cases:
     *    - System-admin role: Immediate bypass — always returns true (full platform access)
     *    - Array of roles + $requireAll: Handles multiple roles correctly (any or all)
     *
     * Why This Is Important:
     * ----------------------
     * - Consistency with UI: When checking "does user have role X in current section?",
     *   school-wide roles should return true
     * - Avoids duplication: No need to assign "bursar" to every section individually
     * - Clean & predictable: Admins see expected behavior across the app
     *
     * Example:
     * - User has:
     *   → "teacher" scoped to Primary (team_id = 1)
     *   → "sport-director" global (team_id = null)
     * - Current section = Primary (team_id = 1)
     *
     * $checker->currentUserHasRole('teacher', $primarySection)           → true  (scoped match)
     * $checker->currentUserHasRole('sport-director', $primarySection)    → true  (global fallback)
     * $checker->currentUserHasRole('sport-director', $secondarySection) → true  (global fallback)
     * $checker->currentUserHasRole('laboratory-instructor', $primarySection) → false
     *
     * @param string|array|BackedEnum $name       Role name(s) to check
     * @param mixed $team                         Team model, instance, ID, or null
     * @param bool $requireAll                    If true, user must have ALL roles in array
     * @return bool                               True if user has the role(s) under current rules
     */
    public function currentUserHasRole(
        string|array|BackedEnum $name,
        mixed $team = null,
        bool $requireAll = false
    ): bool {
        // Standardize input (handles BackedEnum, etc.)
        $name = \Laratrust\Helper::standardize($name);

        // Resolve real team value and requireAll flag (Laratrust helper)
        [
            'team' => $team,
            'require_all' => $requireAll
        ] = $this->getRealValues($team, $requireAll, 'is_bool');

        // === 1. System-admin bypass: full access everywhere ===
        // We use parent call to avoid potential recursion if system-admin is checked later
        if (parent::currentUserHasRole('system-admin', null)) {
            return true;
        }

        // === 2. Handle array of roles ===
        if (is_array($name)) {
            if (empty($name)) {
                return true; // No roles requested → trivially true
            }

            foreach ($name as $roleName) {
                $hasRole = $this->currentUserHasRole($roleName, $team);

                if ($hasRole && !$requireAll) {
                    return true; // One match is enough when requireAll = false
                }

                if (!$hasRole && $requireAll) {
                    return false; // One missing when requireAll = true → fail
                }
            }

            // If we get here:
            // - requireAll = false → none of the roles were found
            // - requireAll = true  → all roles were found
            return $requireAll;
        }

        // === 3. Single role check ===
        $teamId = \Laratrust\Helper::getIdFor($team, 'team');

        // Load cached roles once (efficient)
        $cachedRoles = $this->userCachedRoles();

        // --- Step A: If team provided, check scoped first ---
        if ($teamId !== null) {
            foreach ($cachedRoles as $role) {
                $roleTeamId = $role['pivot'][\Laratrust\Models\Team::modelForeignKey()] ?? null;

                if ($role['name'] === $name && $roleTeamId == $teamId) {
                    return true; // Exact scoped match
                }
            }
        }

        // --- Step B: Fallback to global (team_id = null) ---
        // This applies whether team was provided or not
        foreach ($cachedRoles as $role) {
            $roleTeamId = $role['pivot'][\Laratrust\Models\Team::modelForeignKey()] ?? null;

            if ($role['name'] === $name && $roleTeamId === null) {
                return true; // Global match
            }
        }

        // No match found
        return false;
    }

    /**
     * Check if the current user has the given permission(s).
     *
     * Custom Scoping + Global Fallback Behavior:
     * -----------------------------------------
     * This method implements the same flexible scoping logic as currentUserHasRole(),
     * but extended to cover both:
     *   1. Direct permissions (assigned directly to the user via permission_user pivot)
     *   2. Inherited permissions (coming from the user's roles via permission_role pivot)
     *
     * Desired Logic (when a team/section is provided):
     * ------------------------------------------------
     * 1. System-admin bypass: immediate true
     * 2. If a specific team is passed:
     *    - First: Check direct permissions AND role-inherited permissions that are scoped to that team
     *    - If not found: Fall back to checking direct + inherited permissions that are global (team_id = null)
     * 3. If no team is passed: standard Laratrust behavior (check anywhere)
     *
     * Why This Is Critical:
     * ---------------------
     * - Consistency: Permissions must follow the exact same scoping rules as roles
     * - Real-world use: A global role like "sport-director" grants permissions (e.g., "manage-sports")
     *   → These must be valid in any section context without duplicating assignments
     * - Direct permissions: Some users may have extra permissions assigned directly (scoped or global)
     *
     * Example:
     * - User has:
     *   → Role "teacher" scoped to Primary → grants "grade-exams"
     *   → Role "sport-director" global → grants "manage-sports"
     *   → Direct permission "view-reports" global
     * - Current section = Primary
     *
     * $checker->currentUserHasPermission('grade-exams', $primarySection)     → true  (from scoped role)
     * $checker->currentUserHasPermission('manage-sports', $primarySection)  → true  (from global role)
     * $checker->currentUserHasPermission('view-reports', $primarySection)  → true  (direct global)
     * $checker->currentUserHasPermission('manage-sports', $secondarySection) → true (global fallback)
     *
     * @param string|array|BackedEnum $permission  Permission name(s) to check
     * @param mixed $team                          Team model, instance, ID, or null
     * @param bool $requireAll                     If true, must have ALL permissions
     * @return bool                                True if user has the permission(s)
     */
    public function currentUserHasPermission(
        string|array|BackedEnum $permission,
        mixed $team = null,
        bool $requireAll = false
    ): bool {
        // Standardize permission input (handles enums, etc.)
        $permission = \Laratrust\Helper::standardize($permission);

        // Resolve real team and requireAll values using Laratrust helper
        [
            'team' => $team,
            'require_all' => $requireAll
        ] = $this->getRealValues($team, $requireAll, 'is_bool');

        // === 1. System-admin bypass: full access ===
        if (parent::currentUserHasRole('system-admin', null)) {
            return true;
        }

        // === 2. Handle array of permissions ===
        if (is_array($permission)) {
            if (empty($permission)) {
                return true; // No permissions requested → trivially true
            }

            foreach ($permission as $permName) {
                $hasPerm = $this->currentUserHasPermission($permName, $team);

                if ($hasPerm && !$requireAll) {
                    return true; // One match sufficient
                }

                if (!$hasPerm && $requireAll) {
                    return false; // One missing → fail
                }
            }

            // Final result based on requireAll
            return $requireAll;
        }

        // === 3. Single permission check ===
        $teamId = \Laratrust\Helper::getIdFor($team, 'team');

        // Load cached data once for efficiency
        $cachedPermissions = $this->userCachedPermissions(); // direct permissions
        $cachedRoles = $this->userCachedRoles();       // for inherited permissions

        $teamForeignKey = \Laratrust\Models\Team::modelForeignKey();

        // Helper to check if a pivot item belongs to the target team or is global
        $matchesTeamOrGlobal = function ($item) use ($teamId, $teamForeignKey) {
            $itemTeamId = $item['pivot'][$teamForeignKey] ?? null;

            return $itemTeamId == $teamId || $itemTeamId === null;
        };

        // --- Step A: Scoped check first (only if team provided) ---
        if ($teamId !== null) {
            // 1. Direct permissions scoped to this team
            foreach ($cachedPermissions as $perm) {
                if (
                    \Illuminate\Support\Str::is($permission, $perm['name']) &&
                    ($perm['pivot'][$teamForeignKey] ?? null) == $teamId
                ) {
                    return true;
                }
            }

            // 2. Inherited from roles scoped to this team
            foreach ($cachedRoles as $roleData) {
                if (($roleData['pivot'][$teamForeignKey] ?? null) == $teamId) {
                    $role = $this->hidrateRole(
                        \Illuminate\Support\Facades\Config::get('laratrust.models.role'),
                        $roleData
                    );

                    if ($role->hasPermission($permission)) {
                        return true;
                    }
                }
            }
        }

        // --- Step B: Global fallback (team_id = null) ---
        // 1. Direct global permissions
        foreach ($cachedPermissions as $perm) {
            if (
                \Illuminate\Support\Str::is($permission, $perm['name']) &&
                ($perm['pivot'][$teamForeignKey] ?? null) === null
            ) {
                return true;
            }
        }

        // 2. Inherited from global roles
        foreach ($cachedRoles as $roleData) {
            if (($roleData['pivot'][$teamForeignKey] ?? null) === null) {
                $role = $this->hidrateRole(
                    \Illuminate\Support\Facades\Config::get('laratrust.models.role'),
                    $roleData
                );

                if ($role->hasPermission($permission)) {
                    return true;
                }
            }
        }

        // No match found
        return false;
    }

    /**
     * Creates a model from an array filled with the class data.
     *
     * This is the exact private method from Laratrust's UserDefaultChecker
     * (note the intentional spelling "hidrate" – a historical typo in the package).
     *
     * We replicate it here inline in our custom checker because it is private
     * and cannot be called from child classes.
     */
    private function hidrateRole(string $class, Model|array $data): Role
    {
        if ($data instanceof Model) {
            return $data;
        }

        if (!isset($data['pivot'])) {
            throw new \Exception("The 'pivot' attribute in the {$class} is hidden");
        }

        $role = new $class;
        $primaryKey = $role->getKeyName();

        $role
            ->setAttribute($primaryKey, $data[$primaryKey])
            ->setAttribute('name', $data['name'])
            ->setRelation(
                'pivot',
                MorphPivot::fromRawAttributes($role, $data['pivot'], 'pivot_table')
            );

        return $role;
    }

    /**
     * Flush the user's role and permission cache.
     *
     * Usually safe to keep as-is (calls parent).
     *
     * @return void
     */
    public function currentUserFlushCache()
    {
        parent::currentUserFlushCache();
    }

    // You can add helper methods here if needed, e.g.:
    // protected function hasGlobalRole(...)
    // protected function hasScopedRole(...)
}
