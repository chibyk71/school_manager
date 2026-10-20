<?php

namespace App\Support\Entitlements\Exceptions;

use RuntimeException;

final class UnknownEntitlementException extends RuntimeException
{
    public function __construct(public readonly string $entitlementKey)
    {
        parent::__construct("Unknown entitlement [{$entitlementKey}].");
    }
}
