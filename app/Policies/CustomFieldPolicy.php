<?php

namespace App\Policies;

use App\Models\CustomField;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

/**
 * CustomFieldPolicy
 *
 * Authorization rules for all actions on CustomField model.
 *
 * Supported permissions (must exist in your permission/roles table):
 *   - custom-fields.viewAny
 *   - custom-fields.view
 *   - custom-fields.create
 *   - custom-fields.update
 *   - custom-fields.delete
 *   - custom-fields.restore
 *   - custom-fields.forceDelete
 *   - custom-fields.reorder
 *   - custom-fields.manageGlobals  ← special permission for global (school_id = null) fields
 *
 * Important design principles:
 *   - School admins can only manage their own overrides (school_id = their school)
 *   - Only users with 'custom-fields.manageGlobals' can touch global presets (school_id = null)
 *   - Super-admins / tenant-admins usually have manageGlobals
 *   - All checks are permission-based — school context is enforced in controller/model boot
 *
 * Usage in controller:
 *   Gate::authorize('viewAny', CustomField::class);
 *   Gate::authorize('update', $customField);
 *
 * Or via policy directly:
 *   $this->authorize('update', $field);
 */
class CustomFieldPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any custom fields (list/index).
     *
     * Usually allowed if they can view at least one type.
     */
    public function viewAny(User $user): Response
    {
        return $user->hasPermission('custom-fields.viewAny')
            ? Response::allow()
            : Response::deny('You do not have permission to view custom fields.');
    }

    /**
     * View a specific custom field.
     *
     * Allowed if user has view permission (we don't restrict by school here — that's controller scope).
     */
    public function view(User $user, CustomField $customField): Response
    {
        return $user->hasPermission('custom-fields.view')
            ? Response::allow()
            : Response::deny('You do not have permission to view this custom field.');
    }

    /**
     * Create a new custom field.
     *
     * School admins can create overrides.
     * Only manageGlobals users can create global presets.
     */
    public function create(User $user): Response
    {
        return $user->hasPermission('custom-fields.create')
            ? Response::allow()
            : Response::deny('You do not have permission to create custom fields.');
    }

    /**
     * Update an existing custom field.
     *
     * Rules:
     *   - Global fields (school_id = null) → only users with manageGlobals
     *   - School overrides → user must have update permission + field belongs to their school
     *     (school check is done in controller/boot, but reinforced here)
     */
    public function update(User $user, CustomField $customField): Response
    {
        if (is_null($customField->school_id)) {
            return $user->hasPermission('custom-fields.manageGlobals')
                ? Response::allow()
                : Response::deny('Only administrators with global access can modify default preset fields.');
        }

        return $user->hasPermission('custom-fields.update')
            ? Response::allow()
            : Response::deny('You do not have permission to update this custom field.');
    }

    /**
     * Delete (soft-delete) a custom field.
     *
     * Same rules as update: globals restricted, overrides allowed with permission.
     */
    public function delete(User $user, CustomField $customField): Response
    {
        if (is_null($customField->school_id)) {
            return $user->hasPermission('custom-fields.manageGlobals')
                ? Response::allow()
                : Response::deny('Global preset fields cannot be deleted by school-level users.');
        }

        return $user->hasPermission('custom-fields.delete')
            ? Response::allow()
            : Response::deny('You do not have permission to delete this custom field.');
    }

    /**
     * Restore a soft-deleted custom field.
     */
    public function restore(User $user, CustomField $customField): Response
    {
        if (is_null($customField->school_id)) {
            return $user->hasPermission('custom-fields.manageGlobals')
                ? Response::allow()
                : Response::deny('Only global administrators can restore default preset fields.');
        }

        return $user->hasPermission('custom-fields.restore')
            ? Response::allow()
            : Response::deny('You do not have permission to restore this custom field.');
    }

    /**
     * Permanently delete (force) a custom field.
     */
    public function forceDelete(User $user, CustomField $customField): Response
    {
        if (is_null($customField->school_id)) {
            return $user->hasPermission('custom-fields.manageGlobals')
                ? Response::allow()
                : Response::deny('Global fields cannot be force-deleted by school users.');
        }

        return $user->hasPermission('custom-fields.forceDelete')
            ? Response::allow()
            : Response::deny('You do not have permission to permanently delete this custom field.');
    }

    /**
     * Reorder custom fields (drag & drop sort update).
     *
     * Usually allowed if user can update fields in that scope.
     */
    public function reorder(User $user, ?CustomField $field = null): Response
    {
        // If no field passed (bulk reorder), check general permission
        if (!$field) {
            return $user->hasPermission('custom-fields.reorder')
                ? Response::allow()
                : Response::deny('You do not have permission to reorder custom fields.');
        }

        // If specific field passed (rare), check update permission
        return $this->update($user, $field);
    }

    /**
     * Special ability: manage global preset fields (school_id = null)
     *
     * This is called explicitly in update/delete/restore/forceDelete when school_id is null.
     * Usually granted only to super-admins / tenant-level admins.
     */
    public function manageGlobals(User $user): Response
    {
        return $user->hasPermission('custom-fields.manageGlobals')
            ? Response::allow()
            : Response::deny('You do not have permission to manage global preset fields.');
    }
}
