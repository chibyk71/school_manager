<?php

namespace App\Enums\Auth;

enum AccountLifecycle: string
{
    case Provisioned = 'provisioned';
    case Active = 'active';

    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }

    public function canTransitionTo(self $target): bool
    {
        // Only Provisioned → Active is allowed; never Active → Provisioned.
        return $this === self::Provisioned && $target === self::Active;
    }
}
