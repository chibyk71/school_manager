<?php

namespace App\Support\DataTable;

/**
 * Capability allowlist derived from ColumnDefinitionHelper metadata.
 * Distinguishes capability from presentation visibility.
 */
final class DataTableCapabilityMap
{
    /** @param list<array<string, mixed>> $columns */
    public function __construct(private readonly array $columns) {}

    /** @param list<array<string, mixed>> $columns */
    public static function fromColumns(array $columns): self
    {
        return new self($columns);
    }

    /** @return list<array<string, mixed>> */
    public function columns(): array
    {
        return $this->columns;
    }

    public function hasField(string $field): bool
    {
        return $this->find($field) !== null;
    }

    public function isFilterable(string $field): bool
    {
        $col = $this->find($field);

        return $col !== null && ($col['filterable'] ?? true) === true;
    }

    public function isSortable(string $field): bool
    {
        $col = $this->find($field);

        return $col !== null && ($col['sortable'] ?? true) === true;
    }

    public function isSearchable(string $field): bool
    {
        $col = $this->find($field);
        if ($col === null) {
            return false;
        }
        if (array_key_exists('searchable', $col)) {
            return (bool) $col['searchable'];
        }

        return ($col['filterable'] ?? true) === true
            && ($col['filterType'] ?? 'text') === 'text';
    }

    public function isExportable(string $field): bool
    {
        $col = $this->find($field);
        if ($col === null) {
            return false;
        }
        if (array_key_exists('exportable', $col)) {
            return (bool) $col['exportable'];
        }

        return true;
    }

    /** @return list<string> */
    public function searchableFields(): array
    {
        $fields = [];
        foreach ($this->columns as $col) {
            $field = $col['field'] ?? null;
            if (is_string($field) && $field !== '' && $this->isSearchable($field)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /** @return list<string> */
    public function exportableFields(): array
    {
        $fields = [];
        foreach ($this->columns as $col) {
            $field = $col['field'] ?? null;
            if (is_string($field) && $field !== '' && $this->isExportable($field)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /** @return array<string, mixed>|null */
    public function find(string $field): ?array
    {
        foreach ($this->columns as $col) {
            if (($col['field'] ?? null) === $field) {
                return $col;
            }
        }

        return null;
    }
}
