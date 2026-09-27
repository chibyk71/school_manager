<?php

namespace App\Support\DataTable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Backend-driven DataTable export.
 *
 * Reuses canonical DataTable query semantics — does not reconstruct
 * search/filters/sorts from unrelated frontend state.
 *
 * Extension point: synchronous path now; queued execution can be added
 * without changing DataTableExportRequest / selection / column authorization.
 */
final class DataTableExportService
{
    public function __construct(
        private readonly DataTableQueryEngine $engine = new DataTableQueryEngine(),
        private readonly DataTableSelectionResolver $selectionResolver = new DataTableSelectionResolver(),
        private readonly DataTableSelectionNormalizer $selectionNormalizer = new DataTableSelectionNormalizer(),
    ) {}

    public static function make(): self
    {
        return new self(
            DataTableQueryEngine::make(),
            DataTableSelectionResolver::make(),
            DataTableSelectionNormalizer::fromConfig(),
        );
    }

    /**
     * @param  Builder  $authorizedQuery  Already-authorized resource query
     * @param  list<string>  $requestedColumns
     * @return BinaryFileResponse|StreamedResponse
     */
    public function export(
        Builder $authorizedQuery,
        Model $model,
        DataTableExportRequest $request,
        string $filename = 'export',
        array $extraFields = [],
    ): BinaryFileResponse|StreamedResponse {
        $columns = $this->authorizeColumns($model, $request->columns, $extraFields);
        $query = $this->resolveTargetQuery($authorizedQuery, $model, $request, $extraFields);

        $rows = $this->buildRows($query, $columns);

        $export = new DataTableArrayExport($rows, $columns);
        $ext = $request->format === DataTableExportRequest::FORMAT_XLSX ? 'xlsx' : 'csv';
        $writer = $request->format === DataTableExportRequest::FORMAT_XLSX
            ? \Maatwebsite\Excel\Excel::XLSX
            : \Maatwebsite\Excel\Excel::CSV;

        return Excel::download($export, "{$filename}.{$ext}", $writer);
    }

    /**
     * Validate requested columns against exportable capability.
     * Non-exportable columns are rejected (not silently dropped).
     *
     * @param  list<string>  $requested
     * @return list<string>
     */
    public function authorizeColumns(Model $model, array $requested, array $extraFields = []): array
    {
        if ($requested === []) {
            throw DataTableQueryException::malformed('Export requires at least one column.');
        }

        $map = DataTableCapabilityMap::fromColumns(
            \App\Support\ColumnDefinitionHelper::fromModel($model, $extraFields)
        );

        $authorized = [];
        foreach ($requested as $field) {
            if (! is_string($field) || $field === '') {
                throw DataTableQueryException::malformed('Export column names must be non-empty strings.');
            }
            if (! $map->hasField($field)) {
                throw DataTableQueryException::unknownField($field, 'export');
            }
            if (! $map->isExportable($field)) {
                throw DataTableQueryException::notCapable($field, 'exportable');
            }
            $authorized[] = $field;
        }

        return $authorized;
    }

    /**
     * @param  array<string, mixed>  $extraFields
     */
    public function resolveTargetQuery(
        Builder $authorizedQuery,
        Model $model,
        DataTableExportRequest $request,
        array $extraFields = [],
    ): Builder {
        $target = $request->target;
        $liveQuery = $request->query;

        if ($target->isPage()) {
            // Current membership + pagination + sorting
            $dtQuery = $liveQuery;
            $query = $this->engine->applyQuerySemantics(
                clone $authorizedQuery,
                $model,
                $dtQuery,
                $extraFields
            );

            // Apply page window via offset/limit (same semantics as paginate)
            $offset = max(0, ($dtQuery->page - 1) * $dtQuery->perPage);

            return $query->offset($offset)->limit($dtQuery->perPage);
        }

        if ($target->isIds()) {
            $selection = DataTableSelection::ids($target->ids);

            return $this->selectionResolver->apply(
                clone $authorizedQuery,
                $model,
                $selection,
                $extraFields
            );
        }

        // Query target: membership from selection query; sorting from live query; no pagination
        $membership = $target->query ?? new DataTableSelectionQuery(null, []);
        $dtQuery = new DataTableQuery(
            page: 1,
            perPage: 1,
            search: $membership->search,
            filters: $membership->filters,
            sorts: $liveQuery->sorts,
        );

        return $this->engine->applyQuerySemantics(
            clone $authorizedQuery,
            $model,
            $dtQuery,
            $extraFields
        );
    }

    /**
     * @param  list<string>  $columns
     * @return list<list<mixed>>
     */
    private function buildRows(Builder $query, array $columns): array
    {
        $chunkSize = (int) config('tables.export_chunk_size', 1000);
        $rows = [];

        $query->chunk($chunkSize, function (Collection $models) use (&$rows, $columns) {
            foreach ($models as $model) {
                $row = [];
                foreach ($columns as $field) {
                    $row[] = data_get($model, $field);
                }
                $rows[] = $row;
            }
        });

        return $rows;
    }
}
