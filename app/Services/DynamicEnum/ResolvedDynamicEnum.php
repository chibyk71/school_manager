<?php

/**
 * Effective Dynamic Enum configuration for a key (optional school context).
 *
 * Options are merged by canonical value (tenant baseline + school sparse overlay)
 * with tenant-required enforcement applied as derived state only.
 */

namespace App\Services\DynamicEnum;

use Illuminate\Support\Collection;

final class ResolvedDynamicEnum
{
    /**
     * @param  Collection<int, ResolvedDynamicEnumOption>  $options  all effective options (including inactive)
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $description,
        public readonly Collection $options,
        public readonly ?string $definitionId = null,
    ) {}

    /**
     * Active/selectable options only (current selection vocabulary).
     *
     * @return Collection<int, ResolvedDynamicEnumOption>
     */
    public function activeOptions(): Collection
    {
        return $this->options->filter(fn (ResolvedDynamicEnumOption $o) => $o->isActive)->values();
    }

    /**
     * Find an effective option by raw input (canonicalized before lookup).
     */
    public function findByValue(string $input): ?ResolvedDynamicEnumOption
    {
        $canonical = DynamicEnumValue::canonicalize($input);

        if ($canonical === '') {
            return null;
        }

        return $this->options->first(
            fn (ResolvedDynamicEnumOption $o) => $o->value === $canonical
        );
    }

    public function hasValue(string $input): bool
    {
        return $this->findByValue($input) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'definition_id' => $this->definitionId,
            'options' => $this->options->map(fn (ResolvedDynamicEnumOption $o) => $o->toArray())->all(),
        ];
    }
}
