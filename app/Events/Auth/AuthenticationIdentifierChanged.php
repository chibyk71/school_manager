<?php

namespace App\Events\Auth;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuthenticationIdentifierChanged
{
    use Dispatchable, SerializesModels;

    /**
     * @param  'username'|'auth_phone'|'auth_email'  $identifier
     */
    public function __construct(
        public User $account,
        public string $identifier,
    ) {}
}
