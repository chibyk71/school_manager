<?php

/**
 * Thrown when resolving an unknown / unconfigured Dynamic Enum key.
 *
 * Phase 3R requires explicit failure rather than an empty configuration.
 */

namespace App\Services\DynamicEnum;

use RuntimeException;

class DynamicEnumNotConfiguredException extends RuntimeException
{
    public function __construct(string $key)
    {
        parent::__construct("Dynamic Enum definition [{$key}] is not configured.");
    }
}
