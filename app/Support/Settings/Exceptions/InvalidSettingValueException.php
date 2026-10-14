<?php

namespace App\Support\Settings\Exceptions;

use RuntimeException;

class InvalidSettingValueException extends RuntimeException
{
    public function __construct(
        public readonly string $key,
        public readonly string $reason,
        public readonly mixed $value = null,
    ) {
        parent::__construct("Invalid value for setting [{$key}]: {$reason}");
    }
}
