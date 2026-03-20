<?php

namespace App\Policies;

use App\Models\SchoolSection;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * SchoolSectionPolicy — Production-Ready
 *
 * Authorizes all SchoolSection management actions using the permission-based
 * system. Checks are made against individual permissions, NOT role names.
 *
 * ── Why Permission-Based, Not Role-Based ────────────────────────────────
 * This system serves multiple tenants, each with multiple schools. Every
 * tenant defines their own roles and assigns permissions to those roles
 * freely. Role names are tenant-specific and cannot be relied upon as a
 * consistent authorization signal across tenants.
 *
 * The permission names are the stable API contract:
 *   "sections.create" means the same thing in every tenant.
 *   "admin" does not — Tenant A's "admin" may have different capabilities
 *   than Tenant B's "admin".
 *
 * ── Permission Reference ────────────────────────────────────────────────
 * sections.view-any     → access the Settings > Sections page / list
 * sections.view         → view a single section's detail
 * sections.create       → create new sections (template or custom)
 * sections.update       → edit existing sections (triggers source mutation)
 * sections.delete       → soft-delete sections
 * sections.restore      → restore soft-deleted sections
 * sections.force-delete → permanently delete (assign sparingly)
 *
 * Note: bulk-toggle reuses sections.update — toggling active state is
 * considered an update operation. The service verifies each ID belongs
 * to the current school before executing the bulk operation.
 *
 * ── School Ownership Check ──────────────────────────────────────────────
 * Methods that operate on a specific section instance verify that the
 * section belongs to the user's currently active school. This is a second
 * line of defense alongside BelongsToSchool global scope. It catches edge
 * cases where a section object was instantiated directly (cached value,
 * factory, test) and passed to a controller without going through a query.
 *
 * ── Before Hook ─────────────────────────────────────────────────────────
 * The before() method checks for a wildcard permission (sections.*).
 * This allows superadmin-level users to bypass all individual checks
 * without hardcoding role names. Mirrors the wildcard matching already
 * implemented in usePermissions.ts on the frontend.
 *
 * ── What This Policy Does NOT Do ────────────────────────────────────────
 * - Does not check role names (never)
 * - Does not check business rules (active class levels, role assignments)
 *   → those live in SchoolSectionService
 * - Does not check is_active or source fields
 * - Does not check whether templates can be edited differently from custom
 *   → that is a UX concern handled in the frontend modal
 *
 * ── Registration ────────────────────────────────────────────────────────
 * Register in app/Providers/AuthServiceProvider.php:
 *   protected $policies = [
 *       SchoolSection::class => SchoolSectionPolicy::class,
 *   ];
 *
 * Usage in controller:
 *   $this->authorize('create', SchoolSection::class);
 *   $this->authorize('update', $section);
 *
 * @see App\Models\SchoolSection
 * @see App\Services\SchoolSectionService  (business rule enforcement)
 * @see App\Http\Controllers\Settings\SchoolSectionController
 */
class SchoolSectionPolicy
{
    use HandlesAuthorization;

    /**
     * Wildcard bypass — runs before every other policy method.
     *
     * If the user has the wildcard permission 'sections.*', all checks
     * pass immediately. This mirrors the frontend wildcard matching in
     * usePermissions.ts: p.endsWith('*') && permission.startsWith(p.slice(0, -1))
     *
     * Returns null (not true/false) when no wildcard match is found,
     * allowing normal per-method checks to proceed.
     *
     * @param  User    $user
     * @param  string  $ability  The policy method being called
     * @return bool|null
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasPermission('sections.*')) {
            return true;
        }

        // null = fall through to the specific method check
        return null;
    }

    /**
     * Authorize listing all sections (Settings > Sections page).
     * Does not require a specific section instance — school scoping
     * is handled by BelongsToSchool at the query level.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sections.view-any');
    }

    /**
     * Authorize viewing a single section's details.
     * Includes school ownership verification as second-line defense.
     */
    public function view(User $user, SchoolSection $section): bool
    {
        return $user->hasPermission('sections.view')
            && $this->belongsToCurrentSchool($section);
    }

    /**
     * Authorize creating a new section.
     * No instance check needed — the new section doesn't exist yet.
     * school_id is injected server-side in the service, never from input.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('sections.create');
    }

    /**
     * Authorize updating an existing section.
     * Includes school ownership verification.
     *
     * Note: updating a template-sourced section is allowed here.
     * The source mutation (template → custom) is handled automatically
     * by SchoolSectionObserver, not blocked by this policy.
     */
    public function update(User $user, SchoolSection $section): bool
    {
        return $user->hasPermission('sections.update')
            && $this->belongsToCurrentSchool($section);
    }

    /**
     * Authorize soft-deleting a section.
     * Includes school ownership verification.
     *
     * Business rule checks (active class levels, role assignments) are
     * NOT here — they live in SchoolSectionService::delete() which runs
     * after this authorization check passes.
     */
    public function delete(User $user, SchoolSection $section): bool
    {
        return $user->hasPermission('sections.delete')
            && $this->belongsToCurrentSchool($section);
    }

    /**
     * Authorize restoring a soft-deleted section.
     * Includes school ownership verification.
     *
     * Note: restoring a section whose name conflicts with an existing
     * active section is handled by StoreSchoolSectionRequest logic
     * (suggests restore instead of create), not by this policy.
     */
    public function restore(User $user, SchoolSection $section): bool
    {
        return $user->hasPermission('sections.restore')
            && $this->belongsToCurrentSchool($section);
    }

    /**
     * Authorize permanent deletion of a section.
     * Requires a dedicated permission assigned sparingly.
     * Includes school ownership verification.
     *
     * This is intentionally separate from sections.delete to prevent
     * accidental permanent deletion. The service layer additionally
     * enforces pre-delete cleanup (Laratrust role assignments, class
     * levels) before this operation can complete.
     */
    public function forceDelete(User $user, SchoolSection $section): bool
    {
        return $user->hasPermission('sections.force-delete')
            && $this->belongsToCurrentSchool($section);
    }

    // ────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ────────────────────────────────────────────────────────────────────

    /**
     * Verify the section belongs to the user's currently active school.
     *
     * This is a second-line defense alongside BelongsToSchool global scope.
     * BelongsToSchool prevents cross-school data from appearing in queries.
     * This check catches edge cases where a section instance was obtained
     * outside of a standard query (e.g. from cache, factory, or a test).
     *
     * Uses GetSchoolModel() helper — the same function used throughout the
     * codebase to resolve the current school from session/header context.
     *
     * Returns false if no active school context exists, which would indicate
     * an unauthenticated or misconfigured request state.
     *
     * @param  SchoolSection  $section
     * @return bool
     */
    private function belongsToCurrentSchool(SchoolSection $section): bool
    {
        $currentSchool = GetSchoolModel();

        if ($currentSchool === null) {
            return false;
        }

        return $section->school_id === $currentSchool->id;
    }
}
