<?php

namespace App\Enums\FileManager;

/**
 * Explicit ownership of File Manager domain resources (Folder, File).
 *
 * System-owned resources are platform-wide.
 * School-owned resources belong to exactly one school.
 *
 * Ownership is never inferred from school_id IS NULL alone.
 */
enum Ownership: string
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
