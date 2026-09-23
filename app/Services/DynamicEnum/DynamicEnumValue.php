<?php

/**
 * Canonical Dynamic Enum value contract (Phase 3R).
 *
 * Machine identity normalization:
 *   trim surrounding whitespace → lowercase
 *
 * Does NOT slugify, strip internal spaces, remove punctuation,
 * transliterate, or transform numeric strings.
 */

namespace App\Services\DynamicEnum;

final class DynamicEnumValue
{
    /**
     * Canonicalize a Dynamic Enum option value for identity matching.
     */
    public static function canonicalize(string $value): string
    {
        return strtolower(trim($value));
    }

    /**
     * Whether the raw input is empty after trim (invalid identity).
     */
    public static function isEmpty(string $value): bool
    {
        return trim($value) === '';
    }
}
