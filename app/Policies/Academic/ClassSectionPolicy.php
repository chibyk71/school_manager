<?php

namespace App\Policies;

use App\Models\Academic\ClassSection;
use App\Models\Academic\TeacherClassSectionSubject;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ClassSectionPolicy — Authorization for all ClassSection module operations.
 *
 * ── How This Fits Into The Application ───────────────────────────────────────
 * This policy governs who can perform which operations on class sections.
 * It is registered in AuthServiceProvider (or auto-discovered via model naming
 * convention) and called from:
 *   - ClassSectionController via $this->authorize()
 *   - FormRequests via $this->authorize() in the authorize() method
 *   - Frontend via shared auth.permissions (Inertia HandleInertiaRequests)
 *
 * ── Permission Naming Convention ──────────────────────────────────────────────
 * Permissions follow the pattern: {resource}.{action}
 * All permissions referenced here must exist in the permissions table and be
 * assigned to appropriate roles via the seeder/admin panel.
 *
 * Permissions used by this policy:
 *   class-sections.view-any          List all sections (DataTable index)
 *   class-sections.view              View a single section detail
 *   class-sections.create            Create a section manually
 *   class-sections.update            Edit name, room, capacity, status
 *   class-sections.delete            Soft-delete one or more sections
 *   class-sections.restore           Restore soft-deleted sections
 *   class-sections.force-delete      Permanently delete trashed sections
 *   class-sections.bulk-generate     Generate arms in bulk
 *   class-sections.assign-teacher    Assign/change the form teacher
 *   class-sections.manage-subjects   Assign/remove/update subject-teacher assignments
 *   class-sections.reorder           Change display order
 *   class-sections.toggle-status     Activate or deactivate sections
 *
 * ── Role Assumptions ──────────────────────────────────────────────────────────
 * - superadministrator: bypass all checks (before() method)
 * - administrator:      full access to all section operations within their school
 * - academic_staff:     can view sections; limited write access (see per-method docs)
 * - teacher:            can view sections they are assigned to (future scope)
 *
 * ── Team (School Section) Scoping ────────────────────────────────────────────
 * Laratrust team scoping is handled at the middleware/controller level via
 * GetSchoolModel() + BelongsToSchool global scope. By the time this policy
 * is called, the model has already been scoped to the current school.
 * The policy does NOT need to re-check school ownership — it trusts that
 * the BelongsToSchool scope has already filtered correctly.
 *
 * ── CustomUserChecker Integration ────────────────────────────────────────────
 * Laratrust's CustomUserChecker (already in the codebase) provides the
 * global role fallback logic: if a user has 'administrator' globally (no team),
 * hasPermission() returns true even when called with a specific school team.
 * This means global admins automatically pass all checks here.
 */
class ClassSectionPolicy
{
    use HandlesAuthorization;

    /**
     * Superadministrators bypass all policy checks.
     *
     * Called before any other method. Returning true here grants full access
     * without evaluating the specific policy method.
     * Returning null (not false) falls through to the specific method.
     *
     * @param  User    $user
     * @param  string  $ability  The policy method being called
     * @return bool|null
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('superadministrator')) {
            return true;
        }

        return null; // Fall through to specific method
    }

    /**
     * View the DataTable listing of all class sections.
     *
     * Granted to: administrators, academic coordinators, teachers,
     * and any role that needs to see which sections exist.
     * This is a broad permission — most authenticated staff can view the list.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('class-sections.view-any');
    }

    /**
     * View a single class section's detail page.
     *
     * Same audience as viewAny — if you can see the list, you can see the detail.
     * Kept as a separate permission in case you later want to restrict detail
     * views (e.g., teachers can only see their assigned sections).
     */
    public function view(User $user, ClassSection $section): bool
    {
        return $user->hasPermission('class-sections.view');
    }

    /**
     * Create a single class section manually.
     *
     * Restricted to administrators and academic coordinators.
     * Teachers cannot create sections — that is an admin/academic setup task.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('class-sections.create');
    }

    /**
     * Update a class section's details (name, room, capacity, status, display_name).
     *
     * Restricted to administrators and academic coordinators.
     * Status updates (activate/deactivate) use the toggleStatus policy method.
     */
    public function update(User $user, ClassSection $section): bool
    {
        return $user->hasPermission('class-sections.update');
    }

    /**
     * Soft-delete one or more class sections.
     *
     * Restricted to administrators only — deletion affects enrollment,
     * timetables, and result records. Requires deliberate admin action.
     */
    public function delete(User $user, ClassSection $section): bool
    {
        return $user->hasPermission('class-sections.delete');
    }

    /**
     * Restore a soft-deleted class section.
     *
     * Restricted to administrators. Restoring a section re-enables enrollment
     * and timetable assignment for it.
     */
    public function restore(User $user, ClassSection $section): bool
    {
        return $user->hasPermission('class-sections.restore');
    }

    /**
     * Permanently delete a trashed class section.
     *
     * Most restrictive operation. Permanently removes the section and cascades
     * to enrollment pivot records and subject assignments.
     * Restricted to administrators only — this action is irreversible.
     */
    public function forceDelete(User $user, ClassSection $section): bool
    {
        return $user->hasPermission('class-sections.force-delete');
    }

    /**
     * Bulk generate arm sections across one or more class levels.
     *
     * This is an administrative setup task — equivalent to creating multiple
     * sections at once. Requires the same permission as create but named
     * separately to allow fine-grained permission assignment (e.g., grant
     * bulk-generate without allowing manual one-by-one creation during setup).
     */
    public function bulkGenerate(User $user): bool
    {
        return $user->hasPermission('class-sections.bulk-generate');
    }

    /**
     * Assign or change the form teacher (class teacher / form master) for a section.
     *
     * Separate from general update because form teacher assignment is often
     * delegated to an academic coordinator who cannot otherwise edit section
     * structural details (name, capacity, etc.).
     */
    public function assignTeacher(User $user, ClassSection $section): bool
    {
        return $user->hasPermission('class-sections.assign-teacher');
    }

    /**
     * Manage subject-teacher assignments for a section.
     *
     * Covers: assign a subject, remove an assignment, update a role.
     * Single permission for all three operations — they are always granted
     * or denied together (an academic coordinator manages all subject assignments).
     *
     * Note: The $section parameter is included for future use (e.g., restricting
     * to sections within the coordinator's assigned school section). Currently
     * unused beyond the method signature.
     */
    public function manageSubjects(User $user, ClassSection $section): bool
    {
        return $user->hasPermission('class-sections.manage-subjects');
    }

    /**
     * Remove a specific subject-teacher assignment.
     *
     * Delegates to manageSubjects — the same permission covers all
     * subject assignment operations. The $assignment parameter allows
     * future refinement (e.g., a teacher can remove their own assignments).
     */
    public function removeSubjectAssignment(
        User $user,
        TeacherClassSectionSubject $assignment
    ): bool {
        return $user->hasPermission('class-sections.manage-subjects');
    }

    /**
     * Reorder sections within a class level (drag-and-drop sort).
     *
     * Administrative task — reordering affects how sections appear on
     * timetables, reports, and the student-facing portal.
     */
    public function reorder(User $user): bool
    {
        return $user->hasPermission('class-sections.reorder');
    }

    /**
     * Activate or deactivate one or more class sections.
     *
     * Separate from general update because toggle-status is a common bulk
     * operation that academic coordinators may need to perform (e.g., deactivate
     * unused arms at the start of a new session) without full edit rights.
     */
    public function toggleStatus(User $user): bool
    {
        return $user->hasPermission('class-sections.toggle-status');
    }
}
