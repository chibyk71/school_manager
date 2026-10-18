<?php

namespace App\Support\FileManager;

use App\Enums\FileManager\Capability;
use App\Models\FileManager\Folder;
use InvalidArgumentException;

/**
 * Effective folder capabilities for operation-time enforcement.
 *
 * Effective set = Module maximum ∩ Folder configuration
 * (User authorization and tenant boundary are enforced separately.)
 *
 * The module registry is authoritative:
 * - folder configuration cannot elevate beyond the module's current maximum;
 * - a non-empty module_code with no registered definition fails closed
 *   (no effective capabilities), rather than inheriting general-library rights.
 * Retired modules that remain registered keep their declared ceiling so existing
 * data stays operable within that ceiling.
 *
 * - Module (known) + non-empty stored → stored ∩ module maximum
 * - Module (known) + empty stored → module maximum
 * - Module (unknown / unregistered) → [] (fail closed)
 * - No module + non-empty stored → stored (general library)
 * - No module + empty stored → all Capability values
 */
final class FolderCapabilityResolver
{
    /**
     * @return list<string>
     */
    public static function effective(Folder $folder): array
    {
        $stored = $folder->capabilityList();
        $moduleCode = $folder->module_code;

        if (is_string($moduleCode) && $moduleCode !== '') {
            $definition = ModuleDefinitionRegistry::get($moduleCode);
            if ($definition === null) {
                // Unrecognized module must not inherit general-library capabilities.
                return [];
            }

            $ceiling = $definition->maxCapabilityValues();
            if ($stored === []) {
                return $ceiling;
            }

            // Always re-apply the current module ceiling (not only at write time).
            return array_values(array_intersect($stored, $ceiling));
        }

        // Genuine general library (null/empty module_code).
        if ($stored !== []) {
            return $stored;
        }

        return Capability::values();
    }

    public static function allows(Folder $folder, Capability $capability): bool
    {
        return in_array($capability->value, self::effective($folder), true);
    }

    public static function assertAllows(Folder $folder, Capability $capability): void
    {
        if (! self::allows($folder, $capability)) {
            throw new InvalidArgumentException(
                "Folder does not permit the '{$capability->value}' capability."
            );
        }
    }
}
