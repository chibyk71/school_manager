<?php

namespace App\Support\FileManager;

use App\Enums\FileManager\Capability;

/**
 * Immutable code-defined module registration for File Manager.
 *
 * The database cannot invent modules; school configuration cannot elevate
 * capabilities beyond the maximum declared here.
 *
 * @phpstan-type CapabilityList list<value-of<Capability>>
 */
final class ModuleDefinition
{
    /**
     * @param  list<string>  $maxCapabilities  Capability::value strings
     */
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly string $description = '',
        public readonly array $maxCapabilities = [],
        public readonly bool $retired = false,
    ) {
        foreach ($this->maxCapabilities as $cap) {
            if (! is_string($cap) || Capability::tryFrom($cap) === null) {
                throw new \InvalidArgumentException("Unknown capability '{$cap}' for module '{$this->code}'.");
            }
        }
    }

    /**
     * @return list<string>
     */
    public function maxCapabilityValues(): array
    {
        return array_values(array_unique($this->maxCapabilities));
    }

    public function allows(string $capability): bool
    {
        return in_array($capability, $this->maxCapabilityValues(), true);
    }

    /**
     * Cap a proposed capability set to this module's maximum.
     *
     * @param  list<string>  $requested
     * @return list<string>
     */
    public function clampCapabilities(array $requested): array
    {
        $max = $this->maxCapabilityValues();

        return array_values(array_intersect($requested, $max));
    }

    /**
     * True when requested is a subset of the module maximum.
     *
     * @param  list<string>  $requested
     */
    public function acceptsCapabilities(array $requested): bool
    {
        $max = $this->maxCapabilityValues();
        foreach ($requested as $cap) {
            if (! in_array($cap, $max, true)) {
                return false;
            }
        }

        return true;
    }
}
