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
 * - Non-empty folder.capabilities → that list (already clamped on write)
 * - Empty capabilities + module_code → module maximum
 * - Empty capabilities + no module → all Capability values (unconstrained general library)
 */
final class FolderCapabilityResolver
{
    /**
     * @return list<string>
     */
    public static function effective(Folder $folder): array
    {
        $stored = $folder->capabilityList();
        if ($stored !== []) {
            return $stored;
        }

        $moduleCode = $folder->module_code;
        if (is_string($moduleCode) && $moduleCode !== '') {
            $definition = ModuleDefinitionRegistry::get($moduleCode);
            if ($definition !== null) {
                return $definition->maxCapabilityValues();
            }
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
