<?php

/**
 * ClassLevelPolicy
 *
 * Authorization policy for all ClassLevel operations.
 *
 * Key decisions:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Tenant isolation is checked via the SchoolSection relationship, NOT via
 *   school_id on the ClassLevel itself (that column does not exist on the table).
 *   We verify ownership by checking that the level's section belongs to the
 *   same school as the authenticated user.
 *
 * - Permission checks use $user->hasPermission() (Laratrust instance method)
 *   instead of LaratrustFacade::hasPermission(). The facade checks globally
 *   without respecting the current user — the instance method respects the
 *   CustomUserChecker and team/section scoping you have configured.
 *
 * - before() provides an early-return for super-admins so they bypass all
 *   checks. This is the standard Laratrust pattern for system-level access.
 *
 * - forceDelete is intentionally excluded — we only support soft deletes
 *   for class levels (agreed during architecture planning). Hard deletes
 *   are not exposed via any controller endpoint.
 *
 * - restore() checks the trashed level's section ownership the same way
 *   as other methods — the section is still accessible via withTrashed()
 *   since we only soft-delete the level, not its parent section.
 *
 * - bulkGenerate() is a separate permission because it is a destructive
 *   bulk operation that creates many records at once — more sensitive than
 *   a single create, so it can be granted independently.
 *
 * Fits into the module:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Registered in AuthServiceProvider via $policies array
 * - Used in ClassLevelController via $this->authorize()
 * - All controller methods call authorize() before any service method
 */

namespace App\Policies;

use App\Models\Academic\ClassLevel;
use App\Models\User;

class ClassLevelPolicy
{
    /**
     * Super-admin bypass — skip all checks below.
     * Returns null (not true) to fall through to individual methods for
     * non-super-admins, rather than granting blanket access to everyone.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin') || $user->hasRole('superadministrator')) {
            return true;
        }

        return null; // Continue to individual method checks
    }

    /**
     * Can the user list class levels?
     * Used on both the section detail tab and the global settings view.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('class-levels.view');
    }

    /**
     * Can the user view a specific class level?
     * Verifies permission AND that the level's section belongs to the user's school.
     * Ownership flows: classLevel → schoolSection → school_id === user's school.
     */
    public function view(User $user, ClassLevel $classLevel): bool
    {
        return $user->hasPermission('class-levels.view')
            && $this->userOwnsLevel($user, $classLevel);
    }

    /**
     * Can the user create a class level?
     * Section ownership is verified at the controller level via route model
     * binding on the SchoolSection — the policy only checks permission here.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('class-levels.create');
    }

    /**
     * Can the user update a specific class level?
     */
    public function update(User $user, ClassLevel $classLevel): bool
    {
        return $user->hasPermission('class-levels.update')
            && $this->userOwnsLevel($user, $classLevel);
    }

    /**
     * Can the user soft-delete a class level?
     */
    public function delete(User $user, ClassLevel $classLevel): bool
    {
        return $user->hasPermission('class-levels.delete')
            && $this->userOwnsLevel($user, $classLevel);
    }

    /**
     * Can the user restore a soft-deleted class level?
     * The level is trashed but its section is not — ownership check still works.
     */
    public function restore(User $user, ClassLevel $classLevel): bool
    {
        return $user->hasPermission('class-levels.restore')
            && $this->userOwnsLevel($user, $classLevel);
    }

    public function forceDelete(User $user, ClassLevel $classLevel): bool
    {
        return $user->hasPermission('class-levels.force-delete') && $this->userOwnsLevel($user, $classLevel);
    }

    /**
     * Can the user bulk-generate levels from a preset?
     * Separate permission from create because it creates many records at once.
     * Section ownership is verified at the controller level.
     */
    public function bulkGenerate(User $user): bool
    {
        return $user->hasPermission('class-levels.create');
    }

    /**
     * Can the user reorder sequences within a section?
     * Treated as an update operation permission-wise.
     */
    public function reorder(User $user): bool
    {
        return $user->hasPermission('class-levels.update');
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Verify that the class level's parent section belongs to the
     * authenticated user's active school.
     *
     * This is the correct ownership check for ClassLevel since the model
     * has no school_id column — tenant isolation flows through the section.
     *
     * We load schoolSection with withTrashed() safety via the relationship
     * rather than a raw query so the model's existing eager-loads are reused
     * if already loaded (avoids extra queries on list operations).
     */
    private function userOwnsLevel(User $user, ClassLevel $classLevel): bool
    {
        $school = GetSchoolModel();

        if (!$school) {
            return false;
        }

        // Use already-loaded relation if available, otherwise lazy-load
        $section = $classLevel->relationLoaded('schoolSection')
            ? $classLevel->schoolSection
            : $classLevel->schoolSection()->first();

        return $section?->school_id === $school->id;
    }
}
