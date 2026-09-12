<?php

namespace App\Policies;

use App\Models\Academic\Term;
use App\Models\User;
use App\States\Academic\Term\Active as TermActive;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

/**
 * TermPolicy – Authorization for Academic Terms (Phase 3)
 *
 * Ownership: Term → AcademicSession → School (no term.school_id).
 * Permissions: term.view / term.create / term.edit / term.delete / term.restore
 * Lifecycle ops (activate/close) use edit permission unless a dedicated one exists.
 * Reopen is removed in Phase 3.
 */
class TermPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): Response
    {
        return $user->hasPermission('term.view') || $user->hasPermission('terms.view')
            ? Response::allow()
            : Response::deny('You do not have permission to view terms.');
    }

    public function view(User $user, Term $term): Response
    {
        if (! $this->belongsToCurrentSchool($term)) {
            return Response::deny('This term does not belong to your school.');
        }

        return $user->hasPermission('term.view') || $user->hasPermission('terms.view')
            ? Response::allow()
            : Response::deny('You do not have permission to view this term.');
    }

    public function create(User $user): Response
    {
        return $user->hasPermission('term.create') || $user->hasPermission('terms.create')
            ? Response::allow()
            : Response::deny('You do not have permission to create terms.');
    }

    public function update(User $user, Term $term): Response
    {
        if (! $this->belongsToCurrentSchool($term)) {
            return Response::deny('This term does not belong to your school.');
        }

        return $user->hasPermission('term.edit') || $user->hasPermission('terms.update')
            ? Response::allow()
            : Response::deny('You do not have permission to update this term.');
    }

    public function delete(User $user, Term $term): Response
    {
        if (! $this->belongsToCurrentSchool($term)) {
            return Response::deny('This term does not belong to your school.');
        }

        return $user->hasPermission('term.delete') || $user->hasPermission('terms.delete')
            ? Response::allow()
            : Response::deny('You do not have permission to delete this term.');
    }

    public function restore(User $user, Term $term): Response
    {
        if (! $this->belongsToCurrentSchool($term)) {
            return Response::deny('This term does not belong to your school.');
        }

        return $user->hasPermission('term.restore') || $user->hasPermission('terms.restore')
            ? Response::allow()
            : Response::deny('You do not have permission to restore this term.');
    }

    public function forceDelete(User $user, Term $term): Response
    {
        if (! $this->belongsToCurrentSchool($term)) {
            return Response::deny('This term does not belong to your school.');
        }

        return $user->hasPermission('term.delete') || $user->hasPermission('terms.force-delete')
            ? Response::allow()
            : Response::deny('You do not have permission to permanently delete this term.');
    }

    public function close(User $user, Term $term): Response
    {
        if (! $this->belongsToCurrentSchool($term)) {
            return Response::deny('This term does not belong to your school.');
        }

        if (! ($term->state instanceof TermActive)) {
            return Response::deny('Only active terms can be closed.');
        }

        return $user->hasPermission('term.edit')
            || $user->hasPermission('terms.close')
            || $user->hasPermission('terms.update')
            ? Response::allow()
            : Response::deny('You do not have permission to close this term.');
    }

    public function activate(User $user, Term $term): Response
    {
        if (! $this->belongsToCurrentSchool($term)) {
            return Response::deny('This term does not belong to your school.');
        }

        return $user->hasPermission('term.edit') || $user->hasPermission('terms.update')
            ? Response::allow()
            : Response::deny('You do not have permission to activate this term.');
    }

    protected function belongsToCurrentSchool(Term $term): bool
    {
        $currentSchool = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
        if (! $currentSchool) {
            return false;
        }

        $session = $term->relationLoaded('academicSession')
            ? $term->academicSession
            : $term->academicSession()->first();

        if (! $session) {
            return false;
        }

        return (string) $session->school_id === (string) $currentSchool->id;
    }
}
