<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Legacy event: a User record was created.
 *
 * @deprecated Prefer Person (Profile) + AuthenticationAccountCreated domain events.
 * Must not transport plaintext credentials.
 */
class UserCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user) {}

    public function __toString(): string
    {
        return "UserCreated event for user {$this->user->id}";
    }
}
