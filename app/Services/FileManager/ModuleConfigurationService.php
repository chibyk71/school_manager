<?php

namespace App\Services\FileManager;

use App\Models\FileManager\SchoolModuleConfiguration;
use App\Support\FileManager\ModuleDefinition;
use App\Support\FileManager\ModuleDefinitionRegistry;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Per-school module enablement. Lazy provisioning; never elevates registry ceilings.
 */
final class ModuleConfigurationService
{
    /**
     * @return Collection<int, array{definition: ModuleDefinition, configuration: SchoolModuleConfiguration|null}>
     */
    public function listForSchool(string $schoolId): Collection
    {
        $configs = SchoolModuleConfiguration::query()
            ->where('school_id', $schoolId)
            ->get()
            ->keyBy('module_code');

        return collect(ModuleDefinitionRegistry::all())->map(
            static function (ModuleDefinition $definition) use ($configs) {
                return [
                    'definition' => $definition,
                    'configuration' => $configs->get($definition->code),
                ];
            }
        )->values();
    }

    public function isEnabled(string $schoolId, string $moduleCode): bool
    {
        $definition = ModuleDefinitionRegistry::get($moduleCode);
        if ($definition === null || $definition->retired) {
            return false;
        }

        $config = SchoolModuleConfiguration::query()
            ->where('school_id', $schoolId)
            ->where('module_code', $moduleCode)
            ->first();

        // Lazy: no row means enabled by default for active modules.
        if ($config === null) {
            return true;
        }

        return (bool) $config->enabled;
    }

    /**
     * @param  array{enabled?: bool, settings?: array<string, mixed>|null}  $data
     */
    public function configure(string $schoolId, string $moduleCode, array $data): SchoolModuleConfiguration
    {
        $definition = ModuleDefinitionRegistry::require($moduleCode);
        if ($definition->retired) {
            throw new InvalidArgumentException("Module '{$moduleCode}' is retired and cannot be configured.");
        }

        $config = SchoolModuleConfiguration::query()->firstOrNew([
            'school_id' => $schoolId,
            'module_code' => $moduleCode,
        ]);

        if ($config->exists === false) {
            $config->enabled = true;
            $config->settings = null;
        }

        if (array_key_exists('enabled', $data)) {
            $config->enabled = (bool) $data['enabled'];
        }

        if (array_key_exists('settings', $data)) {
            // Re-enable must not silently wipe existing settings unless explicitly provided.
            $config->settings = $data['settings'];
        }

        $config->save();

        return $config->fresh();
    }

    public function find(string $schoolId, string $moduleCode): ?SchoolModuleConfiguration
    {
        return SchoolModuleConfiguration::query()
            ->where('school_id', $schoolId)
            ->where('module_code', $moduleCode)
            ->first();
    }
}
