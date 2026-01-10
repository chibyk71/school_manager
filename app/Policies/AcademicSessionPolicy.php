<?php

namespace App\Policies;

use App\Models\Academic\AcademicSession;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

/**
 * AcademicSessionPolicy – Authorization Policy for Academic Sessions
 *
 * Defines granular permissions for all CRUD operations, soft-delete handling,
 * and force-delete on the AcademicSession model.
 *
 * Security & Best Practices Implemented:
 * ────────────────────────────────────────────────────────────────
 * • Multi-tenant isolation: all actions are scoped to the current school
 *   via `GetSchoolModel()` and strict `school_id` checks
 * • Permission-based authorization using Laratrust (hasPermission)
 * • Consistent pattern across all methods for maintainability
 * • Explicit Response objects where fine-grained control is needed
 * • Protection against unauthorized cross-school access
 * • Soft-delete aware (restore/forceDelete have separate permissions)
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Protects all controller actions on AcademicSessionController
 * • Ensures only school-specific admins/principals can manage sessions
 * • Supports role-based workflows (e.g. admin can create, principal approves activation)
 * • Prepares for future extensions (e.g. special 'activate-session' permission)
 *
 * Permission Naming Convention (used by Laratrust):
 *   view-academic-sessions
 *   create-academic-sessions
 *   update-academic-sessions
 *   delete-academic-sessions
 *   restore-academic-sessions
 *   force-delete-academic-sessions
 *   (Optional future: activate-academic-sessions, close-academic-sessions)
 *
 * Usage in Controllers:
 *   $this->authorize('update', $session);
 *   $this->authorize('delete', $session);
 */
class AcademicSessionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any academic sessions (list/index).
     *
     * Usually granted to school admins, principals, academic officers.
     */
    public function viewAny(User $user): Response
    {
        return $user->hasPermission('view-academic-sessions')
            ? Response::allow()
            : Response::deny('You do not have permission to view academic sessions.');
    }

    /**
     * Determine whether the user can view a specific academic session.
     *
     * Additional check: must belong to the current school context.
     */
    public function view(User $user, AcademicSession $academicSession): Response
    {
        $currentSchool = GetSchoolModel();

        if (! $currentSchool) {
            return Response::deny('No active school context found.');
        }

        if ($academicSession->school_id !== $currentSchool->id) {
            return Response::deny('This academic session belongs to a different school.');
        }

        return $user->hasPermission('view-academic-sessions')
            ? Response::allow()
            : Response::deny('You do not have permission to view this academic session.');
    }

    /**
     * Determine whether the user can create new academic sessions.
     *
     * Typically restricted to super-admins or school-level academic managers.
     */
    public function create(User $user): Response
    {
        return $user->hasPermission('create-academic-sessions')
            ? Response::allow()
            : Response::deny('You do not have permission to create academic sessions.');
    }

    /**
     * Determine whether the user can update an existing academic session.
     *
     * Includes date adjustments (end_date), status changes, etc.
     * Start date immutability is enforced at service layer, not here.
     */
    public function update(User $user, AcademicSession $academicSession): Response
    {
        $currentSchool = GetSchoolModel();

        if (! $currentSchool || $academicSession->school_id !== $currentSchool->id) {
            return Response::deny('This academic session does not belong to your school.');
        }

        return $user->hasPermission('update-academic-sessions')
            ? Response::allow()
            : Response::deny('You do not have permission to update academic sessions.');
    }

    /**
     * Determine whether the user can soft-delete an academic session.
     *
     * Soft delete should be rare — usually only for cleanup of drafts/errors.
     */
    public function delete(User $user, AcademicSession $academicSession): Response
    {
        $currentSchool = GetSchoolModel();

        if (! $currentSchool || $academicSession->school_id !== $currentSchool->id) {
            return Response::deny('This academic session does not belong to your school.');
        }

        // Optional: Prevent deletion of active/current sessions
        if ($academicSession->is_current || $academicSession->status === AcademicSession::STATUS_ACTIVE) {
            return Response::deny('Cannot delete an active or current academic session.');
        }

        return $user->hasPermission('delete-academic-sessions')
            ? Response::allow()
            : Response::deny('You do not have permission to delete academic sessions.');
    }

    /**
     * Determine whether the user can restore a soft-deleted academic session.
     *
     * Useful for recovering accidentally deleted historical records.
     */
    public function restore(User $user, AcademicSession $academicSession): Response
    {
        $currentSchool = GetSchoolModel();

        if (! $currentSchool || $academicSession->school_id !== $currentSchool->id) {
            return Response::deny('This academic session does not belong to your school.');
        }

        return $user->hasPermission('restore-academic-sessions')
            ? Response::allow()
            : Response::deny('You do not have permission to restore academic sessions.');
    }

    /**
     * Determine whether the user can permanently delete an academic session.
     *
     * Extremely restricted — should almost never be allowed in production
     * (historical data preservation is critical in academic systems).
     */
    public function forceDelete(User $user, AcademicSession $academicSession): Response
    {
        $currentSchool = GetSchoolModel();

        if (! $currentSchool || $academicSession->school_id !== $currentSchool->id) {
            return Response::deny('This academic session does not belong to your school.');
        }

        // Optional extra protection: only super-admin or system owner
        if (! $user->hasRole('super-admin')) {
            return Response::deny('Permanent deletion is restricted to super administrators.');
        }

        return $user->hasPermission('force-delete-academic-sessions')
            ? Response::allow()
            : Response::deny('You do not have permission to permanently delete academic sessions.');
    }

    /**
     * Optional: Custom method for activating/closing sessions.
     * (If you later extract these into separate permissions)
     */
    public function activate(User $user, AcademicSession $academicSession): Response
    {
        return $this->update($user, $academicSession);
        // Or: return $user->hasPermission('activate-academic-sessions') ? ... : ...
    }

    /**
     * Optional: Custom method for closing sessions.
     */
    public function close(User $user, AcademicSession $academicSession): Response
    {
        return $this->update($user, $academicSession);
        // Or: return $user->hasPermission('close-academic-sessions') ? ... : ...
    }
}
