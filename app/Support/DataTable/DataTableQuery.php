<?php

namespace App\Support\DataTable;

/**
 * Canonical semantic DataTable query (V1).
 * Presentation concerns must never appear here.
 */
final class DataTableQuery
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 50,
        public readonly ?string $search = null,
        /** @var list<array{field: string, operator: string, value: mixed}> */
        public readonly array $filters = [],
        /** @var list<array{field: string, direction: 'asc'|'desc'}> */
        public readonly array $sorts = [],
    ) {}

    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'perPage' => $this->perPage,
            'search' => $this->search,
            'filters' => $this->filters,
            'sorts' => $this->sorts,
        ];
    }

    public function identityWithoutPage(): array
    {
        return [
            'perPage' => $this->perPage,
            'search' => $this->search,
            'filters' => $this->filters,
            'sorts' => $this->sorts,
        ];
    }
}
