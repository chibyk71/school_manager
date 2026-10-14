<?php

namespace App\Support\Settings;

/**
 * Immutable metadata for a single code-defined setting.
 */
final class SettingDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $groupKey,
        public readonly string $groupLabel,
        public readonly string $type,
        public readonly mixed $default,
        public readonly string $label,
        public readonly ?string $description = null,
        public readonly ?string $intendedScope = null,
    ) {}
}
