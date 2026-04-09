<?php

namespace App\Policies\Academic;

use App\Models\Exam\AssessmentTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * AssessmentTemplatePolicy
 *
 * Templates are a settings-level resource — only admins manage them.
 * Teachers have read-only access (to see which templates exist when
 * browsing exam details).
 */
class AssessmentTemplatePolicy
{
    use HandlesAuthorization;

    public function before(User $user): ?bool
    {
        if ($user->hasRole('super-admin')) return true;
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'principal', 'class_teacher', 'subject_teacher']);
    }

    public function view(User $user, AssessmentTemplate $template): bool
    {
        return $user->hasAnyRole(['admin', 'principal', 'class_teacher', 'subject_teacher']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'principal'])
            && $user->hasPermissionTo('assessment_templates.create');
    }

    public function update(User $user, AssessmentTemplate $template): bool
    {
        return $user->hasAnyRole(['admin', 'principal'])
            && $user->hasPermissionTo('assessment_templates.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'principal'])
            && $user->hasPermissionTo('assessment_templates.delete');
    }
}
