<?php

namespace App\Support\DataTable;

/**
 * Canonical export request: query context + target + columns + format.
 * Keep these concepts separate — do not merge into one universal blob.
 */
final class DataTableExportRequest
{
    public const FORMAT_CSV = 'csv';

    public const FORMAT_XLSX = 'xlsx';

    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        public readonly DataTableQuery $query,
        public readonly DataTableExportTarget $target,
        public readonly array $columns,
        public readonly string $format = self::FORMAT_CSV,
    ) {}

    public function toArray(): array
    {
        return [
            'query' => $this->query->toArray(),
            'target' => $this->target->toArray(),
            'columns' => $this->columns,
            'format' => $this->format,
        ];
    }
}
