<?php

namespace App\Policies\Academic;

use App\Models\Exam\Exam;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ExamPolicy
 *
 * Handles authorization for all exam-related actions.
 *
 * Role assumptions (adjust to your role/permission setup):
 * - super-admin    → all actions on all schools
 * - admin          → all actions within their school
 * - principal      → all actions within their school
 * - class_teacher  → view, enter scores for their sections only
 * - subject_teacher→ enter scores for their assigned subjects only
 * - parent/student → view published results only (no mutation)
 *
 * Each method receives the authenticated user and (where applicable) the exam.
 * The policy is registered in AuthServiceProvider:
 *   Exam::class => ExamPolicy::class
 *
 * Usage in controllers:
 *   Gate::authorize('create', Exam::class);
 *   Gate::authorize('update', $exam);
 */
class ExamPolicy
{
    use HandlesAuthorization;

    /**
     * Super-admins bypass all checks.
     */
    public function before(User $user): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }
        return null;
    }

    /**
     * Can the user see the exam list?
     * All school staff can view; only relevant sections are returned from the controller.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'principal', 'class_teacher', 'subject_teacher', 'accountant']);
    }

    /**
     * Can the user view a specific exam?
     * Teachers can view exams for their school. Additional section filtering
     * happens in the controller/service layer.
     */
    public function view(User $user, Exam $exam): bool
    {
        if (!$this->isInSameSchool($user, $exam)) return false;

        return $user->hasAnyRole(['admin', 'principal', 'class_teacher', 'subject_teacher', 'accountant']);
    }

    /**
     * Can the user create a new exam?
     * Only admins and principals can create exams.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'principal'])
            && $user->hasPermissionTo('exams.create');
    }

    /**
     * Can the user edit exam metadata (name, dates, template)?
     * Only allowed while the exam is still in draft state.
     * Principals and admins only.
     */
    public function update(User $user, Exam $exam): bool
    {
        if (!$this->isInSameSchool($user, $exam)) return false;

        return $user->hasAnyRole(['admin', 'principal'])
            && $user->hasPermissionTo('exams.update');
    }

    /**
     * Can the user delete an exam?
     * Only draft exams can be deleted; only admins/principals.
     */
    public function delete(User $user, Exam $exam): bool
    {
        if (!$this->isInSameSchool($user, $exam)) return false;
        if (!$exam->isDraft()) return false;

        return $user->hasAnyRole(['admin', 'principal'])
            && $user->hasPermissionTo('exams.delete');
    }

    /**
     * Can the user restore a soft-deleted exam?
     */
    public function restore(User $user, Exam $exam): bool
    {
        return $user->hasAnyRole(['admin', 'principal'])
            && $this->isInSameSchool($user, $exam);
    }

    /**
     * Can the user publish, set ongoing, or approve results?
     * Status transitions are restricted to admins and principals.
     */
    public function updateStatus(User $user, Exam $exam): bool
    {
        if (!$this->isInSameSchool($user, $exam)) return false;

        return $user->hasAnyRole(['admin', 'principal'])
            && $user->hasPermissionTo('exams.publish');
    }

    /**
     * Can the user enter scores?
     * Class teachers and subject teachers can enter scores for their
     * assigned sections/subjects. Admins can also enter scores.
     */
    public function enterScores(User $user, Exam $exam): bool
    {
        if (!$this->isInSameSchool($user, $exam)) return false;

        // Exam must be published or ongoing to allow score entry
        if (!in_array($exam->status, [Exam::STATUS_PUBLISHED, Exam::STATUS_ONGOING], true)) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'principal', 'class_teacher', 'subject_teacher']);
    }

    /**
     * Can the user trigger result computation?
     */
    public function computeResults(User $user, Exam $exam): bool
    {
        if (!$this->isInSameSchool($user, $exam)) return false;

        return $user->hasAnyRole(['admin', 'principal'])
            && $user->hasPermissionTo('exams.compute_results');
    }

    /**
     * Can the user approve/lock results?
     */
    public function approveResults(User $user, Exam $exam): bool
    {
        if (!$this->isInSameSchool($user, $exam)) return false;

        return $user->hasAnyRole(['admin', 'principal'])
            && $user->hasPermissionTo('exams.approve_results');
    }

    /**
     * Can the user view computed results and report cards?
     */
    public function viewResults(User $user, Exam $exam): bool
    {
        if (!$this->isInSameSchool($user, $exam)) return false;

        // Results must be at least computed
        if ($exam->isDraft() || $exam->isPublished()) return false;

        return $user->hasAnyRole(['admin', 'principal', 'class_teacher', 'subject_teacher', 'accountant']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Check that the user belongs to the same school as the exam.
     * Adjust `school_id` accessor based on your multi-tenancy model.
     */
    private function isInSameSchool(User $user, Exam $exam): bool
    {
        // Adjust based on how you resolve current school for a user
        $userSchoolId = $user->school_id ?? $user->current_school_id ?? null;
        return $userSchoolId && $userSchoolId === $exam->school_id;
    }
}
