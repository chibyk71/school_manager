<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Legacy event: an admin reset an account password.
 *
 * Must not transport plaintext credentials. Credential delivery is handled by
 * AdminResetPasswordNotification (or UserAccountService::resetPassword) only.
 *
 * @deprecated Prefer UserAccountService::resetPassword controlled delivery.
 */
class UserPasswordResetByAdmin
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $target) {}
}
