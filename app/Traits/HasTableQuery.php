<?php

// app/Traits/HasTableQuery.php
namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Support\ColumnDefinitionHelper;

trait HasTableQuery
{
    public function scopeTableQuery(Builder $query, Request $request, array $extraFields = [])
    {
        $columns = ColumnDefinitionHelper::fromModel($this, $extraFields);

        // Global search
        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($columns, $search) {
                foreach ($columns as $col) {
                    if ($col['filterType'] === 'text') {
                        if ($col['relation']) {
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
                if (!$column || $value === null || $value === '')
                    continue;

                $applyFilter = function (Builder $q, string $field, string $type, $value) {
                    match ($type) {
                        'text' => $q->where($field, 'like', "%{$value}%"),
                        'boolean' => $q->where($field, (bool) $value),
                        'numeric' => $q->where($field, $value),
                        'date' => is_array($value) && count($value) === 2
                        ? $q->whereBetween($field, [$value[0], $value[1]])
                        : $q->whereDate($field, $value),
                        default => $q->where($field, 'like', "%{$value}%"),
                    };
                };

                if ($column['relation']) {
                    $query->whereHas($column['relation'], function ($sub) use ($column, $applyFilter, $value) {
                        $applyFilter($sub, $column['relatedField'], $column['filterType'], $value);
                    });
                } else {
                    $applyFilter($query, $field, $column['filterType'], $value);
                }
            }
        }

        // Sorting
        $sortField = $request->input('sortField', 'id');
        $sortOrder = $request->input('sortOrder', 'asc');
        $sortColumn = collect($columns)->firstWhere('field', $sortField);

        if ($sortColumn && $sortColumn['relation']) {
            $relationName = $sortColumn['relation'];
            $relatedTable = $this->$relationName()->getRelated()->getTable();
            $relatedKey = $this->$relationName()->getLocalKeyName();
            $foreignKey = $this->$relationName()->getForeignKeyName();

            // Join related table for sorting
            $query->leftJoin($relatedTable, "{$this->getTable()}.{$foreignKey}", '=', "{$relatedTable}.{$relatedKey}")
                ->orderBy("{$relatedTable}.{$sortColumn['relatedField']}", $sortOrder)
                ->select("{$this->getTable()}.*");
        } else {
            $query->orderBy($sortField, $sortOrder);
        }

        // Pagination
        $perPage = (int) $request->input('perPage', 10);

        return $query->paginate($perPage)->appends($request->query());
    }
}
