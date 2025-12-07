<?php

namespace App\Traits;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Support\ColumnDefinitionHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Trait HasTableQuery
 *
 * Provides a reusable scope for dynamic table querying with search, filter, sort, and pagination.
 * Supports hidden columns that are exposed to the frontend but not searchable, sortable, or filterable.
 */
trait HasTableQuery
{

    /**
     * Get model-defined hidden table columns.
     *
     * @return array<string> Array of column names.
     */
    public function getHiddenTableColumns(): array
    {
        return is_array($this->hiddenTableColumns) ? $this->hiddenTableColumns : [];
    }

    /**
     * Get columns used for global filtering.
     * Returns $globalFilterFields if defined, otherwise falls back to model's fillable attributes.
     *
     * @return array<string> Array of column names.
     */
    public function getGlobalFilterColumns(): array
    {
        if (!empty($this->globalFilterFields)) {
            return $this->globalFilterFields;
        }
        return property_exists($this, 'fillable') ? $this->fillable : [];
    }

    /**
     * Apply dynamic table query operations (search, filter, sort, paginate).
     *
     * @param Builder $query The Eloquent query builder instance.
     * @param Request $request The HTTP request containing query parameters.
     * @param array $extraFields Additional fields for column definitions.
     * @param array<callable> $customModifiers Custom query modifiers.
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     * @throws \Exception If query fails or no active school is found.
     */
    public function scopeTableQuery(Builder $query, Request $request, array $extraFields = [], array $customModifiers = [])
    {
        try {
            $school = GetSchoolModel();
            // if (!$school) {
            //     throw new \Exception('No active school found.');
            // }

            // Scope query to school
            if (method_exists($this, 'scopeSchool')) {
                $query->school($school?->id);
            }

            // Cache column definitions
            $cacheKey = 'column_definitions_' . get_class($this) . '_' . md5(json_encode($extraFields));
            $columns = Cache::remember($cacheKey, now()->addHours(1), function () use ($extraFields) {
                try {
                    return ColumnDefinitionHelper::fromModel($this, $extraFields);
                } catch (\Exception $e) {
                    Log::error('Failed to generate column definitions: ' . $e->getMessage());
                    throw $e;
                }
            });

            // Validate inputs
            $sortOrder = in_array(strtolower($request->input('sortOrder', 'asc')), ['asc', 'desc']) ? $request->input('sortOrder') : 'asc';
            $perPage = $request->input('perPage', config('tables.default_per_page', 10));
            $search = $request->input('search') ? trim(strip_tags($request->input('search'))) : null;

            // Validate perPage
            if (!in_array($perPage, ['all', 'simple']) && !is_numeric($perPage)) {
                throw new \Exception('Invalid perPage value.');
            }

            // Global search
            if ($search) {
                $query->where(function (Builder $q) use ($columns, $search) {
                    foreach ($columns as $col) {
                        if ($col['hidden']) {
                            continue;
                        }
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

            // Apply column filters (assuming Purity handles this)
            try {
                $query->filter();
            } catch (\Exception $e) {
                Log::warning('Failed to apply filters: ' . $e->getMessage());
            }

            // Apply sorting (assuming Purity handles multi-column sorting)
            if ($request->has('sort')) {
                try {
                    $query->sort();
                } catch (\Exception $e) {
                    Log::warning('Failed to apply sorting: ' . $e->getMessage());
                }
            }

            // Apply custom modifiers
            foreach ($customModifiers as $modifier) {
                if (is_callable($modifier)) {
                    $modifier($query, $request);
                } else {
                    Log::warning('Invalid custom modifier for model ' . get_class($this));
                }
            }

            // Pagination
            if ($perPage === 'all') {
                return $query->get();
            }
            $perPageValue = is_numeric($perPage) ? min(max((int) $perPage, 1), config('tables.max_per_page', 100)) : 10;
            if ($perPage === 'simple') {
                return $query->simplePaginate($perPageValue)->appends($request->query());
            }
            return $query->paginate($perPageValue)->appends($request->query());
        } catch (\Exception $e) {
            Log::error('Table query failed for model ' . get_class($this) . ': ' . $e->getMessage());
            throw $e;
        }
    }
}
