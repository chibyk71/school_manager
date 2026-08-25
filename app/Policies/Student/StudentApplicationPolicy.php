<?php

namespace App\Policies\Student;

use App\Models\Student\StudentApplication;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * StudentApplicationPolicy – Authorization for Student Application Operations
 *
 * Controls who can view, admit, reject, and delete student applications.
 *
 * ── Permission Model ─────────────────────────────────────────────────────────
 * This policy uses Laratrust permission strings. Map these to your roles
 * in DatabaseSeeder or a RolesPermissionsSeeder:
 *
 *   applications.view      → admin, academic, admissions
 *   applications.admit     → admin, admissions
 *   applications.reject    → admin, admissions
 *   applications.delete    → admin
 *   applications.restore   → admin
 *
 * ── Multi-Tenant Safety ──────────────────────────────────────────────────────
 * Every method checks that the application belongs to the user's active school.
 * This prevents cross-tenant data access even if a permission is granted.
 *
 * ── Super-Admin Bypass ───────────────────────────────────────────────────────
 * The `before()` hook grants all permissions to `super-admin` role, which is
 * handled by CustomUserChecker (our Laratrust checker) — no extra logic needed.
 * We still call `before()` explicitly here for policy-level bypass.
 *
 * ── Fits into the Student Management Module ──────────────────────────────────
 * Used by ApplicationController (admin flow) and referenced in route middleware.
 * Register in AuthServiceProvider:
 *   StudentApplication::class => StudentApplicationPolicy::class
 */
class StudentApplicationPolicy
{
    use HandlesAuthorization;

    /**
     * Super-admin bypass — grants all abilities unconditionally.
     * Return null to fall through to the specific ability methods for others.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return null;
    }

    /**
     * Can the user view the application list?
     * Used by ApplicationController::index()
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('applications.view');
    }

    /**
     * Can the user view a specific application?
     * Enforces school-scoping on top of permission check.
     * Used by ApplicationController::show()
     */
    public function view(User $user, StudentApplication $application): bool
    {
        return $user->hasPermission('applications.view')
            && $this->belongsToUserSchool($user, $application);
    }

    /**
     * Can the user admit an application (convert to enrolled student)?
     * Only draft/submitted/paid applications can be admitted — the service
     * enforces state transitions, but we gate the ability here.
     * Used by ApplicationController::admit()
     */
    public function admit(User $user, StudentApplication $application): bool
    {
        return $user->hasPermission('applications.admit')
            && $this->belongsToUserSchool($user, $application);
    }

    /**
     * Can the user reject an application?
     * Used by ApplicationController::reject()
     */
    public function reject(User $user, StudentApplication $application): bool
    {
        return $user->hasPermission('applications.reject')
            && $this->belongsToUserSchool($user, $application);
    }

    /**
     * Can the user soft-delete an application?
     * Used by ApplicationController::destroy()
     */
    public function delete(User $user, StudentApplication $application): bool
    {
        return $user->hasPermission('applications.delete')
            && $this->belongsToUserSchool($user, $application);
    }

    /**
     * Can the user restore a soft-deleted application?
     * Used by ApplicationController::restore()
     */
    public function restore(User $user, StudentApplication $application): bool
    {
        return $user->hasPermission('applications.restore')
            && $this->belongsToUserSchool($user, $application);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Verify the application belongs to the user's currently active school.
     * Prevents cross-tenant access in a shared-DB multi-tenant setup.
     */
    private function belongsToUserSchool(User $user, StudentApplication $application): bool
    {
        $activeSchool = GetSchoolModel();

        return $activeSchool !== null
            && $application->school_id === $activeSchool->id;
    }
}
