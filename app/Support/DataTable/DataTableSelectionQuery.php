<?php

namespace App\Support\DataTable;

/**
 * Membership-only query for selection / export targets.
 * Must never carry page, perPage, or sorts.
 */
final class DataTableSelectionQuery
{
    /**
     * @param  list<array{field: string, operator: string, value: mixed}>  $filters
     */
    public function __construct(
        public readonly ?string $search = null,
        public readonly array $filters = [],
    ) {}

    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'filters' => $this->filters,
        ];
    }

    /**
     * Deterministic identity for equality / snapshot comparison.
     *
     * @return array{search: ?string, filters: list<array{field: string, operator: string, value: mixed}>}
     */
    public function identity(): array
    {
        return $this->toArray();
    }
}
