<?php

namespace App\Enums;

/**
 * Explicit ownership of a storage provider record.
 *
 * System-owned providers are platform-wide configuration.
 * School-owned providers belong to exactly one school.
 *
 * Ownership is never inferred from school_id IS NULL alone.
 */
enum StorageProviderOwnership: string
{
    case System = 'system';
    case School = 'school';

    public function requiresSchool(): bool
    {
        return $this === self::School;
    }

    public function forbidsSchool(): bool
    {
        return $this === self::System;
    }
}
