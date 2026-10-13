<?php

namespace App\Support\Settings\Exceptions;

use RuntimeException;

class InvalidSettingDefinitionException extends RuntimeException
{
    public function __construct(
        public readonly ?string $key,
        string $reason,
    ) {
        $prefix = $key !== null ? "Invalid setting definition [{$key}]: " : 'Invalid setting definition: ';
        parent::__construct($prefix.$reason);
    }
}
