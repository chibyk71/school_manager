<?php

namespace App\Support\DataTable;

/**
 * Backend-declared bulk action capability for a resource.
 * Frontend may render these; it must not invent executable action identifiers.
 */
final class BulkActionCapability
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly ?string $icon = null,
        public readonly bool $requiresConfirmation = false,
        /** @var 'atomic'|'partial'|null */
        public readonly ?string $semantics = null,
    ) {}

    /**
     * @return array{id: string, label: string, icon?: string, requiresConfirmation?: bool, semantics?: string}
     */
    public function toArray(): array
    {
        $out = [
            'id' => $this->id,
            'label' => $this->label,
        ];
        if ($this->icon !== null) {
            $out['icon'] = $this->icon;
        }
        if ($this->requiresConfirmation) {
            $out['requiresConfirmation'] = true;
        }
        if ($this->semantics !== null) {
            $out['semantics'] = $this->semantics;
        }

        return $out;
    }
}
