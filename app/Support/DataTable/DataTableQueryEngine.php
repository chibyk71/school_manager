<?php

namespace App\Support\DataTable;

use App\Support\ColumnDefinitionHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Generic DataTable query processor.
 * CRITICAL: Operates ONLY on an already-authorized Eloquent query.
 * Must never apply tenant/school/section/policy scopes itself.
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
        $this->applyFilters($query, $model, $dtQuery->filters, $capabilities);
        $this->applySorts($query, $model, $dtQuery->sorts, $capabilities);

        $paginator = $query->paginate($dtQuery->perPage, ['*'], 'page', $dtQuery->page);

        $meta = [
            'currentPage' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'lastPage' => $paginator->lastPage(),
        ];

        // Canonical response + legacy top-level keys for in-progress frontend migration
        return [
            'data' => $paginator->items(),
            'columns' => $columns,
            'meta' => $meta,
            // Legacy (to be removed once all consumers use meta)
            'totalRecords' => $meta['total'],
            'currentPage' => $meta['currentPage'],
            'lastPage' => $meta['lastPage'],
            'perPage' => $meta['perPage'],
        ];
    }

    /**
     * Apply search/filter/sort without pagination (export / query selection).
     *
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
        $this->applyFilters($query, $model, $dtQuery->filters, $capabilities);
        $this->applySorts($query, $model, $dtQuery->sorts, $capabilities);

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
    private function applyGlobalSearch(Builder $query, Model $model, ?string $search, array $searchFields, DataTableCapabilityMap $capabilities): void
    {
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

    /** @param list<array{field: string, operator: string, value: mixed}> $filters */
    private function applyFilters(Builder $query, Model $model, array $filters, DataTableCapabilityMap $capabilities): void
    {
        if ($filters === []) {
            return;
        }
        // Explicit Eloquent (flat AND). Purity remains available when request already carries filters.
        foreach ($filters as $filter) {
            $this->applyEloquentFilter($query, $model, $filter, $capabilities);
        }
    }

    /** @param array{field: string, operator: string, value: mixed} $filter */
    private function applyEloquentFilter(Builder $query, Model $model, array $filter, DataTableCapabilityMap $capabilities): void
    {
        $field = $filter['field'];
        $operator = $filter['operator'];
        $value = $filter['value'];
        $col = $capabilities->find($field);
        $relation = $col['relation'] ?? null;
        $relatedField = $col['relatedField'] ?? null;

        $apply = function (Builder $q, string $column) use ($operator, $value) {
            match ($operator) {
                DataTableOperators::EQUALS => $q->where($column, '=', $value),
                DataTableOperators::NOT_EQUALS => $q->where($column, '!=', $value),
                DataTableOperators::CONTAINS => $q->where($column, 'like', '%'.$value.'%'),
                DataTableOperators::NOT_CONTAINS => $q->where($column, 'not like', '%'.$value.'%'),
                DataTableOperators::STARTS_WITH => $q->where($column, 'like', $value.'%'),
                DataTableOperators::ENDS_WITH => $q->where($column, 'like', '%'.$value),
                DataTableOperators::LESS_THAN => $q->where($column, '<', $value),
                DataTableOperators::LESS_THAN_OR_EQUAL => $q->where($column, '<=', $value),
                DataTableOperators::GREATER_THAN => $q->where($column, '>', $value),
                DataTableOperators::GREATER_THAN_OR_EQUAL => $q->where($column, '>=', $value),
                DataTableOperators::IN => $q->whereIn($column, is_array($value) ? $value : [$value]),
                DataTableOperators::NOT_IN => $q->whereNotIn($column, is_array($value) ? $value : [$value]),
                DataTableOperators::BETWEEN => $q->whereBetween($column, $value),
                DataTableOperators::NOT_BETWEEN => $q->whereNotBetween($column, $value),
                DataTableOperators::IS_NULL => $q->whereNull($column),
                DataTableOperators::IS_NOT_NULL => $q->whereNotNull($column),
                default => null,
            };
        };

        if ($relation && $relatedField && method_exists($model, $relation)) {
            $query->whereHas($relation, function (Builder $sub) use ($apply, $relatedField) {
                $apply($sub, $relatedField);
            });
        } elseif (! str_contains($field, '.')) {
            $apply($query, $field);
        } else {
            $parts = explode('.', $field);
            $leaf = array_pop($parts);
            $relationPath = implode('.', $parts);
            if ($relationPath !== '' && $leaf !== null) {
                $query->whereHas($relationPath, function (Builder $sub) use ($apply, $leaf) {
                    $apply($sub, $leaf);
                });
            }
        }
    }

    /** @param list<array{field: string, direction: string}> $sorts */
    private function applySorts(Builder $query, Model $model, array $sorts, DataTableCapabilityMap $capabilities): void
    {
        $applied = [];
        foreach ($sorts as $sort) {
            $field = $sort['field'];
            $direction = $sort['direction'] === 'desc' ? 'desc' : 'asc';
            $col = $capabilities->find($field);
            if (! empty($col['relation'])) {
                if (! str_contains($field, '.')) {
                    $query->orderBy($field, $direction);
                    $applied[] = $field;
                }
                continue;
            }
            $query->orderBy($field, $direction);
            $applied[] = $field;
        }
        $key = $model->getKeyName();
        if ($key && ! in_array($key, $applied, true)) {
            $query->orderBy($model->getTable().'.'.$key, 'asc');
        }
    }
}
