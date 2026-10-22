<?php

namespace App\Support\School;

/**
 * Authoritative school type catalogue for Phase 1.
 *
 * Source of truth aligned with:
 * - Legacy schema default: private
 * - Existing frontend CreateEdit options: private, government
 * - UpdateSchoolRequest historical rule: private, government, community
 *
 * Do not invent additional values here without product approval.
 * Readiness, Store, and Update validation must use this list.
 */
final class SchoolTypeCatalogue
{
    /**
     * @var list<string>
     */
    public const VALUES = [
        'private',
        'government',
        'community',
    ];

    /**
     * Laravel Rule::in compatible list.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return self::VALUES;
    }

    public static function isValid(?string $type): bool
    {
        if ($type === null || $type === '') {
            return false;
        }

        return in_array($type, self::VALUES, true);
    }

    /**
     * Comma-separated for inline validation rule strings.
     */
    public static function ruleIn(): string
    {
        return implode(',', self::VALUES);
    }
}
