<?php

namespace App\Policies;

use App\Models\Academic\AcademicSession;
use App\Models\User;
use App\States\Academic\AcademicSession\Active as SessionActive;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class AcademicSessionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): Response
    {
        return $user->hasPermission('view-academic-sessions')
            ? Response::allow()
            : Response::deny('You do not have permission to view academic sessions.');
    }

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

    public function create(User $user): Response
    {
        return $user->hasPermission('create-academic-sessions')
            ? Response::allow()
            : Response::deny('You do not have permission to create academic sessions.');
    }

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

    public function delete(User $user, AcademicSession $academicSession): Response
    {
        $currentSchool = GetSchoolModel();

        if (! $currentSchool || $academicSession->school_id !== $currentSchool->id) {
            return Response::deny('This academic session does not belong to your school.');
        }

        // Prevent deletion of ACTIVE sessions (authoritative state)
        if ($academicSession->state instanceof SessionActive
            || (string) $academicSession->state === SessionActive::$name) {
            return Response::deny('Cannot delete an active academic session.');
        }

        return $user->hasPermission('delete-academic-sessions')
            ? Response::allow()
            : Response::deny('You do not have permission to delete academic sessions.');
    }

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

    public function forceDelete(User $user, AcademicSession $academicSession): Response
    {
        $currentSchool = GetSchoolModel();

        if (! $currentSchool || $academicSession->school_id !== $currentSchool->id) {
            return Response::deny('This academic session does not belong to your school.');
        }

        if (! $user->hasRole('super-admin')) {
            return Response::deny('Permanent deletion is restricted to super administrators.');
        }

        return $user->hasPermission('force-delete-academic-sessions')
            ? Response::allow()
            : Response::deny('You do not have permission to permanently delete academic sessions.');
    }

    public function activate(User $user, AcademicSession $academicSession): Response
    {
        return $this->update($user, $academicSession);
    }

    public function close(User $user, AcademicSession $academicSession): Response
    {
        return $this->update($user, $academicSession);
    }
}
