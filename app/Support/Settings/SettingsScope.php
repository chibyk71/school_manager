<?php

namespace App\Support\Settings;

/**
 * Canonical scope identifiers for OI Laravel Settings.
 *
 * Phase 1 supports user and school ownership only. Tenant scope is deferred.
 * Encoding prevents collisions between owner kinds that share the same id.
 *
 * Do not treat package global (scope = null) as organizational inheritance.
 * Application code should pass explicit scopes when reading/writing owner values.
 */
final class SettingsScope
{
    public static function user(string $userId): string
    {
        return 'user:'.$userId;
    }

    public static function school(string $schoolId): string
    {
        return 'school:'.$schoolId;
    }
}
