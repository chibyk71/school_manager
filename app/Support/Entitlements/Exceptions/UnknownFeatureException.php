<?php

namespace App\Support\Entitlements\Exceptions;

use RuntimeException;

final class UnknownFeatureException extends RuntimeException
{
    public function __construct(public readonly string $featureKey)
    {
        parent::__construct("Unknown feature [{$featureKey}].");
    }
}
