<?php

namespace App\Support\Entitlements\Exceptions;

use RuntimeException;

final class InsufficientCapacityException extends RuntimeException
{
    public function __construct(
        public readonly string $entitlementKey,
        public readonly int|float $requested,
        public readonly int|float $available,
        public readonly int|float $limit,
    ) {
        parent::__construct(
            "Insufficient capacity for [{$entitlementKey}]: requested {$requested}, "
            ."available {$available}, limit {$limit}."
        );
    }
}
