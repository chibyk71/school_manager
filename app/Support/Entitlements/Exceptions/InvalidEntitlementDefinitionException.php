<?php

namespace App\Support\Entitlements\Exceptions;

use InvalidArgumentException;

final class InvalidEntitlementDefinitionException extends InvalidArgumentException
{
    public function __construct(
        public readonly ?string $entitlementKey,
        string $message,
    ) {
        parent::__construct(
            $entitlementKey !== null
                ? "Invalid entitlement definition [{$entitlementKey}]: {$message}"
                : "Invalid entitlement definition: {$message}"
        );
    }
}
