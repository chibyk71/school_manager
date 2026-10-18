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
 * The module registry is authoritative: folder configuration cannot elevate
 * capabilities beyond the module's current maximum, even if older stored data
 * predates a ceiling change or is otherwise inconsistent.
 *
 * - Module + non-empty stored → stored ∩ module maximum
 * - Module + empty stored → module maximum
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
            if ($definition !== null) {
                $ceiling = $definition->maxCapabilityValues();
                if ($stored === []) {
                    return $ceiling;
                }

                // Always re-apply the current module ceiling (not only at write time).
                return array_values(array_intersect($stored, $ceiling));
            }
        }

        // No module (or unknown definition): general-library behavior.
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
