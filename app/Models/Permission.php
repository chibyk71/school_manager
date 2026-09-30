<?php

namespace App\Models;

use Laratrust\Models\Permission as PermissionModel;

/**
 * Permission — system-defined capability (Permission Phase 2).
 *
 * Permissions are application-owned. Administrators must not create, modify,
 * or delete permission definitions. The catalogue is established and
 * synchronized via seeders / application code (e.g. PermissionSeeder).
 *
 * A permission represents a capability (what can be done), not a role,
 * membership, section, resource instance, or business rule.
 *
 * Identity: unique name (stable system identity).
 * Presentation: display_name, description (optional).
 *
 * Scope is not stored on the permission row. Authorization scope is applied
 * at assignment time (role_user / permission_user school_id) and by the
 * future authorization layer — not via a permission-scope registry.
 */
class Permission extends PermissionModel
{
    public $guarded = [];
}
