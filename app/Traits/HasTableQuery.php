<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Support\ColumnDefinitionHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Trait HasTableQuery
 *
 * Provides a reusable scope for dynamic table querying with search, filter, sort, and pagination.
 */
trait HasTableQuery
{
    /**
     * Apply dynamic table query operations (search, filter, sort, paginate) based on request parameters.
     *
     * @param Builder $query The Eloquent query builder instance.
     * @param Request $request The HTTP request containing query parameters.
     * @param array $extraFields Additional fields for column definitions.
     * @param array<callable> $customModifiers Custom query modifiers.
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     */
    public function scopeTableQuery(Builder $query, Request $request, array $extraFields = [], array $customModifiers = [])
    {
        // Cache column definitions for performance
        $cacheKey = 'column_definitions_' . get_class($this) . '_' . md5(json_encode($extraFields));
        $columns = Cache::remember($cacheKey, now()->addHours(1), fn() => ColumnDefinitionHelper::fromModel($this, $extraFields));
        $validFields = collect($columns)->pluck('field')->toArray();

        // Validate inputs
        $sortField = in_array($request->input('sortField', 'id'), $validFields) ? $request->input('sortField') : 'id';
        $sortOrder = in_array(strtolower($request->input('sortOrder', 'asc')), ['asc', 'desc']) ? $request->input('sortOrder') : 'asc';
        $perPage = $request->input('perPage', config('tables.default_per_page', 10));
        $search = $request->input('search') ? trim(strip_tags($request->input('search'))) : null;

        // Global search
        if ($search) {
            $query->where(function (Builder $q) use ($columns, $search) {
                foreach ($columns as $col) {
                    if ($col['filterable'] && $col['filterType'] === 'text') {
                        if ($col['relation'] && method_exists($this, $col['relation'])) {
                            $q->orWhereHas($col['relation'], function ($sub) use ($col, $search) {
                                $sub->where($col['relatedField'], 'like', "%{$search}%");
                            });
                        } else {
                            $q->orWhere($col['field'], 'like', "%{$search}%");
                        }
                    }
                }
            });
        }

        // Column filters
        if ($filters = $request->input('filters', [])) {
            foreach ($filters as $field => $value) {
                $column = collect($columns)->firstWhere('field', $field);
                if (!$column || $value === null || $value === '' || !$column['filterable']) {
                    continue;
                }

                $applyFilter = function (Builder $q, string $field, string $type, $value, ?callable $customFilter = null) {
                    if ($customFilter) {
                        $customFilter($q, $field, $value);
                    } else {
                        match ($type) {
                            'text' => $q->where($field, 'like', "%{$value}%"),
                            'boolean' => $q->where($field, (bool) $value),
                            'numeric' => $q->where($field, $value),
                            'date' => is_array($value) && count($value) === 2
                                ? $q->whereBetween($field, [$value[0], $value[1]])
                                : $q->whereDate($field, $value),
                            'in' => $q->whereIn($field, (array) $value),
                            default => $q->where($field, 'like', "%{$value}%"),
                        };
                    }
                };

                if ($column['relation'] && method_exists($this, $column['relation'])) {
                    $query->whereHas($column['relation'], function ($sub) use ($column, $applyFilter, $value) {
                        $applyFilter($sub, $column['relatedField'], $column['filterType'], $value, $column['customFilter'] ?? null);
                    });
                } else {
                    $applyFilter($query, $field, $column['filterType'], $value, $column['customFilter'] ?? null);
                }
            }
        }

        // Sorting
        $sortColumn = collect($columns)->firstWhere('field', $sortField);
        if ($sortColumn && $sortColumn['sortable'] && $sortColumn['relation'] && method_exists($this, $sortColumn['relation'])) {
            $relationName = $sortColumn['relation'];
            if (!$query->getQuery()->joins) { // Avoid duplicate joins
                $relatedTable = $this->$relationName()->getRelated()->getTable();
                $relatedKey = $this->$relationName()->getLocalKeyName();
                $foreignKey = $this->$relationName()->getForeignKeyName();
                $query->leftJoin($relatedTable, "{$this->getTable()}.{$foreignKey}", '=', "{$relatedTable}.{$relatedKey}")
                    ->orderBy("{$relatedTable}.{$sortColumn['relatedField']}", $sortOrder)
                    ->select("{$this->getTable()}.*");
            } else {
                Log::warning("Duplicate join detected for relation '{$relationName}' in model " . get_class($this));
            }
        } else {
            if ($sortColumn && $sortColumn['sortable']) {
                $query->orderBy($sortField, $sortOrder);
            } else {
                Log::warning("Invalid sort field '{$sortField}' in model " . get_class($this));
                $query->orderBy('id', $sortOrder); // Fallback to default sort
            }
        }

        // Apply custom modifiers
        foreach ($customModifiers as $modifier) {
            if (is_callable($modifier)) {
                $modifier($query, $request);
            } else {
                Log::warning("Invalid custom modifier provided for model " . get_class($this));
            }
        }

        // Pagination
        if ($perPage === 'all') {
            return $query->get();
        }
        if ($perPage === 'simple') {
            return $query->simplePaginate(min(max((int) $perPage, 1), config('tables.max_per_page', 100)))->appends($request->query());
        }
        return $query->paginate(min(max((int) $perPage, 1), config('tables.max_per_page', 100)))->appends($request->query());
    }
}
