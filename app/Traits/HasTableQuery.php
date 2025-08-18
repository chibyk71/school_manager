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
 * Supports hidden columns (not searchable, sortable, or filterable but still exposed to the frontend).
 */
trait HasTableQuery
{
    /**
     * Columns that should never be searchable/sortable/filterable (model-level hidden columns).
     * Override this in your model if needed.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [];

    /**
     * Columns that should be used, when preforming global search on a model
     * 
     * @var array<string>
     */
    protected array $globalFilterFields = [];

    /**
     * Get model-defined hidden table columns.
     *
     * @return array<string>
     */
    public function getHiddenTableColumns(): array
    {
        return $this->hiddenTableColumns ?? [];
    }

    /**
     * Get the columns used for global filtering.
     * Returns $globalFilterFields if not empty, otherwise falls back to model's fillable attributes.
     *
     * @return array<string>
     */
    public function getGlobalFilterColumns(): array
    {
        if (!empty($this->globalFilterFields)) {
            return $this->globalFilterFields;
        }
        return property_exists($this, 'fillable') ? $this->fillable : [];
    }

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

        // Validate inputs
        $sortOrder = in_array(strtolower($request->input('sortOrder', 'asc')), ['asc', 'desc']) ? $request->input('sortOrder') : 'asc';
        $perPage = $request->input('perPage', config('tables.default_per_page', 10));
        $search = $request->input('search') ? trim(strip_tags($request->input('search'))) : null;

        // Global search
        if ($search) {
            $query->where(function (Builder $q) use ($columns, $search) {
                foreach ($columns as $col) {
                    if ($col['hidden']) continue; // skip hidden

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
        $query->filter();

           // ✅ Apply sorting using Purity (handles multi-column + direction)
        // If no sort provided, fallback to a safe default
        if ($request->has('sort')) {
            $query->sort();
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
