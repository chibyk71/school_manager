<?php

namespace App\Support\DataTable;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Thin Laravel Excel wrapper for pre-built export rows.
 * Server-side formatting belongs here / in the export service — not in Vue formatters.
 */
final class DataTableArrayExport implements FromArray, WithHeadings
{
    /**
     * @param  list<list<mixed>>  $rows
     * @param  list<string>  $headings
     */
    public function __construct(
        private readonly array $rows,
        private readonly array $headings,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
