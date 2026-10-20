<?php

namespace App\Support\Entitlements\Exceptions;

use RuntimeException;

final class InvalidEntitlementValueException extends RuntimeException
{
    public function __construct(
        public readonly string $entitlementKey,
        string $message,
        public readonly mixed $value = null,
    ) {
        parent::__construct(
            "Invalid entitlement value for [{$entitlementKey}]: {$message}"
        );
    }
}
