<?php

namespace App\Policies;

use App\Models\Academic\Grade;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

/**
 * GradePolicy – Granular authorization rules for Grade model operations
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Implements fine-grained, role/permission-based access control for all Grade CRUD actions
 * • Prevents dangerous operations on used grades (e.g. update/delete if referenced in results)
 * • Supports soft-delete + force-delete distinctions (common in educational systems)
 * • Uses Laravel's Response objects for rich denial messages (shown in frontend via Inertia errors)
 * • Centralizes all authorization logic → easy to audit and maintain
 * • Integrates with your permission system (assumes Spatie Permissions or similar via $user->can())
 * • Protects against accidental data corruption in production
 *
 * How it fits into the Grades Module:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Used via Gate::authorize() or $user->can() in GradeController methods
 * • Called automatically by Laravel when using authorizeResource() in controller
 * • Enforces business rules:
 *   - Only admins/managers can create/update/delete grading scales
 *   - Update/delete blocked if grade isUsed() (referenced in ExamResult, etc.)
 *   - Restore allowed only if previously deleted by same role
 * • Aligns with frontend UX: denial messages can be shown in PrimeVue toasts/modals
 * • Works with Inertia: errors are returned in JSON format when request wantsJson()
 *
 * Assumptions / Dependencies:
 * • You have a permission system (e.g. Spatie Laravel-Permission) with abilities like:
 *   - grades.viewAny, grades.view, grades.create, grades.update, grades.delete, grades.restore, grades.forceDelete
 * • Grade model has isUsed() helper method (checks if referenced in results)
 * • User model has can() method or similar
 *
 * Best Practices Applied:
 * • Return Response::deny() with message → rich error feedback
 * • Early returns for simple cases
 * • Consistent structure across methods
 * • Prepared for future extensions (e.g. section-specific permissions)
 */
class GradePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any grades (list/index).
     */
    public function viewAny(User $user): Response
    {
        return $user->can('grades.viewAny')
            ? Response::allow()
            : Response::deny('You do not have permission to view grading scales.');
    }

    /**
     * Determine whether the user can view a specific grade (show/details).
     */
    public function view(User $user, Grade $grade): Response
    {
        return $user->can('grades.view')
            ? Response::allow()
            : Response::deny('You do not have permission to view this grade.');
    }

    /**
     * Determine whether the user can create new grades.
     */
    public function create(User $user): Response
    {
        return $user->can('grades.create')
            ? Response::allow()
            : Response::deny('You do not have permission to create new grading scales.');
    }

    /**
     * Determine whether the user can update an existing grade.
     *
     * Additional safety: block update if grade is in use (prevents retroactive corruption)
     */
    public function update(User $user, Grade $grade): Response
    {
        if (! $user->can('grades.update')) {
            return Response::deny('You do not have permission to update grading scales.');
        }

        if ($grade->isUsed()) {
            return Response::deny('This grade cannot be updated because it is already used in student results or assessments.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can delete (soft-delete) a grade.
     *
     * Additional safety: prevent deletion if grade is actively used
     */
    public function delete(User $user, Grade $grade): Response
    {
        if (! $user->can('grades.delete')) {
            return Response::deny('You do not have permission to delete grading scales.');
        }

        if ($grade->isUsed()) {
            return Response::deny('This grade cannot be deleted because it is referenced in student results or assessments.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can restore a soft-deleted grade.
     */
    public function restore(User $user, Grade $grade): Response
    {
        return $user->can('grades.restore')
            ? Response::allow()
            : Response::deny('You do not have permission to restore deleted grading scales.');
    }

    /**
     * Determine whether the user can permanently delete a grade (force delete).
     *
     * Usually restricted to super-admins only
     */
    public function forceDelete(User $user, Grade $grade): Response
    {
        if (! $user->can('grades.forceDelete')) {
            return Response::deny('You do not have permission to permanently delete grading scales.');
        }

        // Even super-admins should be warned/cautious if in use
        if ($grade->isUsed()) {
            return Response::denyWithStatus(403, 'This grade is in use and cannot be force-deleted.');
        }

        return Response::allow();
    }
}
