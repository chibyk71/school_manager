<?php

namespace App\Support\Settings\Exceptions;

use RuntimeException;

class UnknownSettingException extends RuntimeException
{
    public function __construct(
        public readonly string $key,
        ?string $message = null,
    ) {
        parent::__construct($message ?? "Unknown setting key [{$key}].");
    }
}
