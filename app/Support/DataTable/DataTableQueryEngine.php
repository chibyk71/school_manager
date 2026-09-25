<?php

namespace App\Support\DataTable;

use App\Support\ColumnDefinitionHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Generic DataTable query processor.
 *
 * CRITICAL: Operates ONLY on an already-authorized Eloquent query.
 * Must never apply tenant/school/section/policy scopes itself.
 *
 * Pipeline:
 *   request → canonical DataTableQuery → capability validation
 *   → global search → Purity filter/sort → PK tie-breaker → pagination → response
 */
final class DataTableQueryEngine
{
    public function __construct(
        private readonly DataTableQueryNormalizer $normalizer = new DataTableQueryNormalizer(),
    ) {}

    public static function make(?DataTableQueryNormalizer $normalizer = null): self
    {
        return new self($normalizer ?? DataTableQueryNormalizer::fromConfig());
    }

    /**
     * @param  Builder  $query  Already-authorized Eloquent builder
     * @param  Request|DataTableQuery|array<string, mixed>  $input
     * @param  array<string, mixed>  $extraFields
     * @return array{data: mixed, columns: list<array<string, mixed>>, meta: array{currentPage: int, perPage: int, total: int, lastPage: int}}
     */
    public function process(Builder $query, Model $model, Request|DataTableQuery|array $input, array $extraFields = []): array
    {
        $dtQuery = match (true) {
            $input instanceof DataTableQuery => $input,
            $input instanceof Request => $this->normalizer->fromRequest($input),
            default => $this->normalizer->fromArray($input),
        };

        $columns = ColumnDefinitionHelper::fromModel($model, $extraFields);
        $capabilities = DataTableCapabilityMap::fromColumns($columns);
        $searchFields = $this->resolveSearchFields($model, $capabilities);

        $this->validateFilters($dtQuery->filters, $capabilities);
        $this->validateSorts($dtQuery->sorts, $capabilities);

        $this->applyGlobalSearch($query, $model, $dtQuery->search, $searchFields, $capabilities);
        $this->applyFiltersViaPurity($query, $model, $dtQuery->filters, $capabilities);
        $this->applySortsViaPurity($query, $model, $dtQuery->sorts, $capabilities);
        $this->applyPrimaryKeyTieBreaker($query, $model, $dtQuery->sorts);

        $paginator = $query->paginate($dtQuery->perPage, ['*'], 'page', $dtQuery->page);

        $meta = [
            'currentPage' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'lastPage' => $paginator->lastPage(),
        ];

        // Canonical V1 response. Temporary legacy pagination keys remain for
        // still-unmigrated consumers; searchable fields come from columns[].searchable.
        return [
            'data' => $paginator->items(),
            'columns' => $columns,
            'meta' => $meta,
            'totalRecords' => $meta['total'],
            'currentPage' => $meta['currentPage'],
            'lastPage' => $meta['lastPage'],
            'perPage' => $meta['perPage'],
        ];
    }

    /**
     * @param  array<string, mixed>  $extraFields
     */
    public function applyQuerySemantics(Builder $query, Model $model, DataTableQuery $dtQuery, array $extraFields = []): Builder
    {
        $columns = ColumnDefinitionHelper::fromModel($model, $extraFields);
        $capabilities = DataTableCapabilityMap::fromColumns($columns);
        $searchFields = $this->resolveSearchFields($model, $capabilities);

        $this->validateFilters($dtQuery->filters, $capabilities);
        $this->validateSorts($dtQuery->sorts, $capabilities);

        $this->applyGlobalSearch($query, $model, $dtQuery->search, $searchFields, $capabilities);
        $this->applyFiltersViaPurity($query, $model, $dtQuery->filters, $capabilities);
        $this->applySortsViaPurity($query, $model, $dtQuery->sorts, $capabilities);
        $this->applyPrimaryKeyTieBreaker($query, $model, $dtQuery->sorts);

        return $query;
    }

    /** @return list<string> */
    private function resolveSearchFields(Model $model, DataTableCapabilityMap $capabilities): array
    {
        if (method_exists($model, 'getGlobalFilterColumns')) {
            $declared = $model->getGlobalFilterColumns();
            if (is_array($declared) && $declared !== []) {
                return array_values(array_filter(
                    $declared,
                    fn ($f) => is_string($f) && $capabilities->hasField($f) && $capabilities->isSearchable($f)
                ));
            }
        }

        return $capabilities->searchableFields();
    }

    /** @param list<array{field: string, operator: string, value: mixed}> $filters */
    private function validateFilters(array $filters, DataTableCapabilityMap $capabilities): void
    {
        foreach ($filters as $filter) {
            $field = $filter['field'];
            $operator = $filter['operator'];
            $value = $filter['value'];
            if (! $capabilities->hasField($field)) {
                throw DataTableQueryException::unknownField($field, 'filtering');
            }
            if (! $capabilities->isFilterable($field)) {
                throw DataTableQueryException::notCapable($field, 'filterable');
            }
            if (! DataTableOperators::isValid($operator)) {
                throw DataTableQueryException::unsupportedOperator($field, $operator);
            }
            if (! DataTableOperators::isNullary($operator) && ($value === null || $value === '')) {
                throw DataTableQueryException::invalidValue($field, $operator);
            }
            if (DataTableOperators::expectsArray($operator) && ! is_array($value)) {
                throw DataTableQueryException::invalidValue($field, $operator);
            }
            if (in_array($operator, [DataTableOperators::BETWEEN, DataTableOperators::NOT_BETWEEN], true)
                && (! is_array($value) || count($value) !== 2)) {
                throw DataTableQueryException::invalidValue($field, $operator);
            }
        }
    }

    /** @param list<array{field: string, direction: string}> $sorts */
    private function validateSorts(array $sorts, DataTableCapabilityMap $capabilities): void
    {
        foreach ($sorts as $sort) {
            $field = $sort['field'];
            if (! $capabilities->hasField($field)) {
                throw DataTableQueryException::unknownField($field, 'sorting');
            }
            if (! $capabilities->isSortable($field)) {
                throw DataTableQueryException::notCapable($field, 'sortable');
            }
        }
    }

    /** @param list<string> $searchFields */
    private function applyGlobalSearch(
        Builder $query,
        Model $model,
        ?string $search,
        array $searchFields,
        DataTableCapabilityMap $capabilities,
    ): void {
        if ($search === null || $search === '' || $searchFields === []) {
            return;
        }

        $query->where(function (Builder $q) use ($model, $search, $searchFields, $capabilities) {
            foreach ($searchFields as $field) {
                $col = $capabilities->find($field);
                $relation = $col['relation'] ?? null;
                $relatedField = $col['relatedField'] ?? null;

                if ($relation && $relatedField && method_exists($model, $relation)) {
                    $q->orWhereHas($relation, function (Builder $sub) use ($relatedField, $search) {
                        $sub->where($relatedField, 'like', "%{$search}%");
                    });
                } elseif (! str_contains($field, '.')) {
                    $q->orWhere($field, 'like', "%{$search}%");
                } else {
                    $parts = explode('.', $field);
                    $leaf = array_pop($parts);
                    $relationPath = implode('.', $parts);
                    if ($relationPath !== '' && $leaf !== null) {
                        $q->orWhereHas($relationPath, function (Builder $sub) use ($leaf, $search) {
                            $sub->where($leaf, 'like', "%{$search}%");
                        });
                    }
                }
            }
        });
    }

    /**
     * @param  list<array{field: string, operator: string, value: mixed}>  $filters
     */
    private function applyFiltersViaPurity(
        Builder $query,
        Model $model,
        array $filters,
        DataTableCapabilityMap $capabilities,
    ): void {
        if ($filters === []) {
            return;
        }

        if (! $this->modelUsesFilterable($model)) {
            throw DataTableQueryException::malformed(
                'Model ['.get_class($model).'] must use Abbasudo\\Purity\\Traits\\Filterable for DataTable filtering.'
            );
        }

        $purityParams = $this->toPurityFilterParams($filters, $capabilities);

        try {
            $query->filter($purityParams);
        } catch (\Throwable $e) {
            Log::warning('DataTable Purity filter failed', [
                'model' => get_class($model),
                'message' => $e->getMessage(),
            ]);
            throw DataTableQueryException::malformed('Filter execution failed: '.$e->getMessage());
        }
    }

    /**
     * @param  list<array{field: string, direction: string}>  $sorts
     */
    private function applySortsViaPurity(
        Builder $query,
        Model $model,
        array $sorts,
        DataTableCapabilityMap $capabilities,
    ): void {
        if ($sorts === []) {
            return;
        }

        if (! $this->modelUsesSortable($model)) {
            throw DataTableQueryException::malformed(
                'Model ['.get_class($model).'] must use Abbasudo\\Purity\\Traits\\Sortable for DataTable sorting.'
            );
        }

        $puritySorts = [];
        foreach ($sorts as $sort) {
            $path = $this->puritySortPath($sort['field'], $capabilities);
            $direction = $sort['direction'] === 'desc' ? 'desc' : 'asc';
            $puritySorts[] = $path.':'.$direction;
        }

        try {
            $query->sort($puritySorts);
        } catch (\Throwable $e) {
            Log::warning('DataTable Purity sort failed', [
                'model' => get_class($model),
                'message' => $e->getMessage(),
            ]);
            throw DataTableQueryException::malformed('Sort execution failed: '.$e->getMessage());
        }
    }

    /**
     * @param  list<array{field: string, direction: string}>  $sorts
     */
    private function applyPrimaryKeyTieBreaker(Builder $query, Model $model, array $sorts): void
    {
        $key = $model->getKeyName();
        if (! $key) {
            return;
        }

        $applied = array_map(fn ($s) => $s['field'], $sorts);
        if (in_array($key, $applied, true)) {
            return;
        }

        $query->orderBy($model->getTable().'.'.$key, 'asc');
    }

    /**
     * @param  list<array{field: string, operator: string, value: mixed}>  $filters
     * @return array<string, mixed>
     */
    private function toPurityFilterParams(array $filters, DataTableCapabilityMap $capabilities): array
    {
        $params = [];
        $opMap = DataTableOperators::toPurity();

        foreach ($filters as $filter) {
            $purityOp = $opMap[$filter['operator']] ?? null;
            if ($purityOp === null) {
                throw DataTableQueryException::unsupportedOperator($filter['field'], $filter['operator']);
            }

            $segments = $this->purityFieldSegments($filter['field'], $capabilities);
            $value = DataTableOperators::isNullary($filter['operator'])
                ? true
                : $filter['value'];

            $leaf = array_pop($segments);
            $node = [$purityOp => $value];
            $node = [$leaf => $node];
            while ($segments !== []) {
                $seg = array_pop($segments);
                $node = [$seg => $node];
            }

            $params = array_replace_recursive($params, $node);
        }

        return $params;
    }

    private function puritySortPath(string $field, DataTableCapabilityMap $capabilities): string
    {
        return implode('.', $this->purityFieldSegments($field, $capabilities));
    }

    /** @return list<string> */
    private function purityFieldSegments(string $field, DataTableCapabilityMap $capabilities): array
    {
        $col = $capabilities->find($field);
        $relation = is_array($col) ? ($col['relation'] ?? null) : null;
        $relatedField = is_array($col) ? ($col['relatedField'] ?? null) : null;

        if (is_string($relation) && $relation !== '' && is_string($relatedField) && $relatedField !== '') {
            $parts = array_values(array_filter(explode('.', $relation), fn ($p) => $p !== ''));
            $parts[] = $relatedField;

            return $parts;
        }

        if (str_contains($field, '.')) {
            return array_values(array_filter(explode('.', $field), fn ($p) => $p !== ''));
        }

        return [$field];
    }

    private function modelUsesFilterable(Model $model): bool
    {
        return method_exists($model, 'scopeFilter')
            || in_array(\Abbasudo\Purity\Traits\Filterable::class, class_uses_recursive($model), true);
    }

    private function modelUsesSortable(Model $model): bool
    {
        return method_exists($model, 'scopeSort')
            || in_array(\Abbasudo\Purity\Traits\Sortable::class, class_uses_recursive($model), true);
    }
}
