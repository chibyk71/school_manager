<?php

namespace App\Policies;

use App\Models\Academic\Term;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

/**
 * TermPolicy – Authorization Policy for Academic Terms
 *
 * Defines granular permissions for all CRUD operations, soft-delete handling,
 * force-delete, and specialized actions (close/reopen) on the Term model.
 *
 * Security & Best Practices Implemented:
 * ────────────────────────────────────────────────────────────────
 * • Multi-tenant isolation: all actions are strictly scoped to the current school
 *   via `GetSchoolModel()` and `school_id` checks
 * • Permission-based authorization using Laratrust (hasPermission) with consistent naming
 * • Explicit Response objects with meaningful denial messages (shown in frontend)
 * • Protection against cross-school access (critical in multi-tenant SaaS)
 * • Extra business safety: prevent modification/deletion of active/closed terms where needed
 * • Future-ready: separate permissions for close/reopen (can be assigned independently)
 * • Soft-delete aware: restore & forceDelete have distinct permissions
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Protects all controller actions on TermController & TermClosureController
 * • Ensures only authorized school-level users (admin, principal, academic officer)
 *   can manage terms within their school context
 * • Supports role-based workflows:
 *   - Admin creates/edits terms
 *   - Principal closes/reopens terms
 *   - Academic officer views/uses terms for assessments
 * • Aligns perfectly with TermClosureService (close/reopen require specific perms)
 *
 * Permission Naming Convention (recommended for Laratrust):
 *   terms.view
 *   terms.create
 *   terms.update
 *   terms.delete
 *   terms.restore
 *   terms.force-delete
 *   terms.close          ← New: for closing active terms
 *   terms.reopen         ← New: for restricted reopening of closed terms
 *
 * Usage in Controllers:
 *   $this->authorize('update', $term);
 *   $this->authorize('close', $term);    // custom method
 *   $this->authorize('reopen', $term);   // custom method
 */
class TermPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any terms (list/index).
     *
     * Usually granted to school admins, principals, academic officers.
     */
    public function viewAny(User $user): Response
    {
        return $user->hasPermission('terms.view')
            ? Response::allow()
            : Response::deny('You do not have permission to view academic terms.');
    }

    /**
     * Determine whether the user can view a specific term.
     *
     * Additional check: must belong to the current school context.
     */
    public function view(User $user, Term $term): Response
    {
        $currentSchool = GetSchoolModel();

        if (! $currentSchool) {
            return Response::deny('No active school context found.');
        }

        if ($term->school_id !== $currentSchool->id) {
            return Response::deny('This term belongs to a different school.');
        }

        return $user->hasPermission('terms.view')
            ? Response::allow()
            : Response::deny('You do not have permission to view this term.');
    }

    /**
     * Determine whether the user can create new terms.
     *
     * Typically restricted to school-level academic managers.
     */
    public function create(User $user): Response
    {
        return $user->hasPermission('terms.create')
            ? Response::allow()
            : Response::deny('You do not have permission to create new academic terms.');
    }

    /**
     * Determine whether the user can update an existing term.
     *
     * Includes date adjustments, name changes, etc.
     * Start date immutability is enforced at service layer, not policy.
     */
    public function update(User $user, Term $term): Response
    {
        $currentSchool = GetSchoolModel();

        if (! $currentSchool || $term->school_id !== $currentSchool->id) {
            return Response::deny('This term does not belong to your school.');
        }

        // Optional extra protection: Prevent update if term is closed (unless explicitly allowed)
        if ($term->is_closed && ! $user->hasPermission('terms.update-closed')) {
            return Response::deny('Cannot update a closed term.');
        }

        return $user->hasPermission('terms.update')
            ? Response::allow()
            : Response::deny('You do not have permission to update academic terms.');
    }

    /**
     * Determine whether the user can soft-delete a term.
     *
     * Soft delete should be rare — usually only for cleanup of drafts/errors.
     */
    public function delete(User $user, Term $term): Response
    {
        $currentSchool = GetSchoolModel();

        if (! $currentSchool || $term->school_id !== $currentSchool->id) {
            return Response::deny('This term does not belong to your school.');
        }

        // Safety: Prevent deletion of active or closed terms
        if ($term->is_active || $term->is_closed) {
            return Response::deny('Cannot delete an active or closed term.');
        }

        return $user->hasPermission('terms.delete')
            ? Response::allow()
            : Response::deny('You do not have permission to delete academic terms.');
    }

    /**
     * Determine whether the user can restore a soft-deleted term.
     *
     * Useful for recovering accidentally deleted terms.
     */
    public function restore(User $user, Term $term): Response
    {
        $currentSchool = GetSchoolModel();

        if (! $currentSchool || $term->school_id !== $currentSchool->id) {
            return Response::deny('This term does not belong to your school.');
        }

        return $user->hasPermission('terms.restore')
            ? Response::allow()
            : Response::deny('You do not have permission to restore academic terms.');
    }

    /**
     * Determine whether the user can permanently delete a term.
     *
     * Extremely restricted — historical preservation is critical.
     */
    public function forceDelete(User $user, Term $term): Response
    {
        $currentSchool = GetSchoolModel();

        if (! $currentSchool || $term->school_id !== $currentSchool->id) {
            return Response::deny('This term does not belong to your school.');
        }

        // Extra protection: only super-admin or system owner
        if (! $user->hasRole('super-admin')) {
            return Response::deny('Permanent deletion is restricted to super administrators.');
        }

        return $user->hasPermission('terms.force-delete')
            ? Response::allow()
            : Response::deny('You do not have permission to permanently delete academic terms.');
    }

    // ────────────────────────────────────────────────────────────────
    // Specialized Actions – Closure & Reopen (used by TermClosureController)
    // ────────────────────────────────────────────────────────────────

    /**
     * Determine whether the user can close an active term.
     *
     * Often requires higher privilege (principal/admin) due to downstream effects.
     */
    public function close(User $user, Term $term): Response
    {
        $currentSchool = GetSchoolModel();

        if (! $currentSchool || $term->school_id !== $currentSchool->id) {
            return Response::deny('This term does not belong to your school.');
        }

        if (! $term->is_active) {
            return Response::deny('Only active terms can be closed.');
        }

        return $user->hasPermission('terms.close')
            ? Response::allow()
            : Response::deny('You do not have permission to close this term.');
    }

    /**
     * Determine whether the user can reopen a previously closed term.
     *
     * Highly restricted operation — requires elevated privileges.
     */
    public function reopen(User $user, Term $term): Response
    {
        $currentSchool = GetSchoolModel();

        if (! $currentSchool || $term->school_id !== $currentSchool->id) {
            return Response::deny('This term does not belong to your school.');
        }

        if (! $term->is_closed) {
            return Response::deny('This term is not closed and cannot be reopened.');
        }

        return $user->hasPermission('terms.reopen')
            ? Response::allow()
            : Response::deny('You do not have permission to reopen this term.');
    }
}
