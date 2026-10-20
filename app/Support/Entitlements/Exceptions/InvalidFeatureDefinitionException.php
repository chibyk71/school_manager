<?php

namespace App\Support\Entitlements\Exceptions;

use InvalidArgumentException;

final class InvalidFeatureDefinitionException extends InvalidArgumentException
{
    public function __construct(
        public readonly ?string $featureKey,
        string $message,
    ) {
        parent::__construct(
            $featureKey !== null
                ? "Invalid feature definition [{$featureKey}]: {$message}"
                : "Invalid feature definition: {$message}"
        );
    }
}
