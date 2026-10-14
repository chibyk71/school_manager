<?php

namespace App\Support\Settings\Exceptions;

use RuntimeException;

class InvalidStoredSettingValueException extends RuntimeException
{
    public function __construct(
        public readonly string $key,
        public readonly string $scope,
        public readonly string $reason,
        public readonly mixed $value = null,
    ) {
        parent::__construct(
            "Invalid stored value for setting [{$key}] at scope [{$scope}]: {$reason}"
        );
    }
}
