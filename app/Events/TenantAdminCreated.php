<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after the initial Tenant system admin Authentication Account is created.
 *
 * Must never transport plaintext credentials. Credential return for bootstrap
 * commands is handled by TenantAdminBootstrapService return values only.
 */
class TenantAdminCreated
{
    use Dispatchable;

    public function __construct(public User $user) {}
}
