<?php

namespace App\Support\Settings;

/**
 * A named group of setting definitions registered together.
 *
 * @phpstan-type DefinitionList array<string, SettingDefinition>
 */
final class SettingGroupDefinition
{
    /**
     * @param  array<string, SettingDefinition>  $definitions
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $definitions,
    ) {}
}
