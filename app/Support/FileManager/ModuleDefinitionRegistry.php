<?php

namespace App\Support\FileManager;

use App\Enums\FileManager\Capability;
use InvalidArgumentException;

/**
 * Authoritative code-defined registry of File Manager modules.
 *
 * Follows the same developer-owned pattern as PermissionCatalogue / SettingsRegistry:
 * existence and capability ceilings live in code; per-school enablement lives in DB.
 *
 * Retiring a definition does not delete stored folders, files, attachments, or
 * school configuration rows.
 */
final class ModuleDefinitionRegistry
{
    /** @var array<string, ModuleDefinition>|null */
    private static ?array $definitions = null;

    /**
     * @return array<string, ModuleDefinition>
     */
    public static function all(): array
    {
        return self::definitions();
    }

    public static function get(string $code): ?ModuleDefinition
    {
        return self::definitions()[$code] ?? null;
    }

    public static function require(string $code): ModuleDefinition
    {
        $definition = self::get($code);
        if ($definition === null) {
            throw new InvalidArgumentException("Unknown File Manager module code '{$code}'.");
        }

        return $definition;
    }

    public static function has(string $code): bool
    {
        return isset(self::definitions()[$code]);
    }

    /**
     * Active (non-retired) definitions only.
     *
     * @return array<string, ModuleDefinition>
     */
    public static function active(): array
    {
        return array_filter(
            self::definitions(),
            static fn (ModuleDefinition $d) => ! $d->retired
        );
    }

    /**
     * Reset cached definitions (tests only).
     */
    public static function flush(): void
    {
        self::$definitions = null;
    }

    /**
     * @return array<string, ModuleDefinition>
     */
    private static function definitions(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        $allCapabilities = Capability::values();

        $list = [
            new ModuleDefinition(
                code: 'general',
                label: 'General',
                description: 'Default school file library for general documents.',
                maxCapabilities: $allCapabilities,
            ),
            new ModuleDefinition(
                code: 'academic',
                label: 'Academic',
                description: 'Academic materials, syllabi, and lesson resources.',
                maxCapabilities: $allCapabilities,
            ),
            new ModuleDefinition(
                code: 'student',
                label: 'Student',
                description: 'Student-related documents and attachments.',
                maxCapabilities: [
                    Capability::Browse->value,
                    Capability::Upload->value,
                    Capability::Download->value,
                    Capability::Attach->value,
                    Capability::Rename->value,
                    Capability::Delete->value,
                ],
            ),
            new ModuleDefinition(
                code: 'employee',
                label: 'Employee',
                description: 'Employee HR and administrative documents.',
                maxCapabilities: [
                    Capability::Browse->value,
                    Capability::Upload->value,
                    Capability::Download->value,
                    Capability::Attach->value,
                    Capability::Rename->value,
                    Capability::Delete->value,
                ],
            ),
            new ModuleDefinition(
                code: 'finance',
                label: 'Finance',
                description: 'Financial documents and receipts.',
                maxCapabilities: [
                    Capability::Browse->value,
                    Capability::Upload->value,
                    Capability::Download->value,
                    Capability::Attach->value,
                    Capability::Rename->value,
                    Capability::Move->value,
                    Capability::Delete->value,
                ],
            ),
        ];

        $map = [];
        foreach ($list as $definition) {
            $map[$definition->code] = $definition;
        }

        return self::$definitions = $map;
    }
}
