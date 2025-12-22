<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Support\ColumnDefinitionHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Trait HasTableQuery v2.1
 *
 * Powerful, reusable Eloquent scope for advanced DataTable queries.
 *
 * Features:
 * - Hybrid data fetching: full dataset (full_load), windowed prefetch, or standard pagination
 * - Automatic column definitions via ColumnDefinitionHelper (with visibility controls)
 * - Global search across text-filterable columns (including relations)
 * - Full Laravel Purity integration for advanced filtering & multi-sorting
 * - Support for default hidden columns (sent to frontend but initially hidden)
 * - Custom query modifiers (e.g., permission-based scopes, status filters)
 * - Comprehensive error logging and input sanitization
 */
trait HasTableQuery
{
    /**
     * Columns that should NEVER be sent to the frontend.
     * Typically sensitive data like passwords, tokens, etc.
     *
     * @return array<string>
     */
    public function getHiddenTableColumns(): array
    {
        return property_exists($this, 'hiddenTableColumns') && is_array($this->hiddenTableColumns)
            ? $this->hiddenTableColumns
            : [];
    }

    /**
     * Columns that ARE sent to the frontend but are hidden by default.
     * Users can toggle visibility via column chooser.
     *
     * @return array<string>
     */
    public function getDefaultHiddenColumns(): array
    {
        return property_exists($this, 'defaultHiddenColumns') && is_array($this->defaultHiddenColumns)
            ? $this->defaultHiddenColumns
            : [];
    }

    /**
     * Fields used for global search (free-text search box).
     * If not defined, falls back to model's $fillable.
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
     * Main entry point: Dynamic table query with full hybrid support.
     *
     * @param Builder $query Eloquent query builder instance
     * @param Request $request Incoming HTTP request with query params
     * @param array $extraFields Optional field overrides/merges (headers, sortable, etc.)
     * @param array<callable> $customModifiers Additional query closures (e.g., permissions)
     *
     * @return array Response array containing data, totals, columns, and pagination info
     */
    public function scopeTableQuery(Builder $query, Request $request, array $extraFields = [], array $customModifiers = [])
    {
        try {
            // Multi-tenant support: fetch current school model
            $school = GetSchoolModel();

            // Apply school scoping if the model supports it
            if (method_exists($this, 'scopeSchool')) {
                $query->school($school?->id);
            }

            // Cache column definitions per model + extraFields (1 hour)
            // Ensures consistent columns across paginated/windowed requests
            $cacheKey = 'column_definitions_' . get_class($this) . '_' . md5(json_encode($extraFields));
            $columns = Cache::remember($cacheKey, now()->addHours(1), function () use ($extraFields) {
                return ColumnDefinitionHelper::fromModel($this, $extraFields);
            });

            // Sanitize and validate core pagination/search inputs
            $perPage = $request->input('per_page', config('tables.default_per_page', 20));
            $page    = max(1, (int) $request->input('page', 1));
            $search  = $request->filled('search') ? trim(strip_tags($request->input('search'))) : null;

            // Hybrid mode flags
            $isFullLoad = $request->boolean('full_load'); // Client-side mode when small dataset
            $isWindow   = $request->boolean('window') || ((int) $perPage > config('tables.max_per_page', 100));

            // =================================================================
            // Global Search (free-text across multiple columns)
            // =================================================================
            if ($search) {
                $query->where(function (Builder $q) use ($columns, $search) {
                    foreach ($columns as $col) {
                        // Skip hidden, non-filterable, or non-text columns
                        if ($col['hidden'] || !$col['filterable'] || $col['filterType'] !== 'text') {
                            continue;
                        }

                        // Relation-based search (e.g., user.profile.name)
                        if ($col['relation'] && method_exists($this, $col['relation'])) {
                            $q->orWhereHas($col['relation'], function ($subQuery) use ($col, $search) {
                                $subQuery->where($col['relatedField'], 'like', "%{$search}%");
                            });
                        } else {
                            // Direct column search
                            $q->orWhere($col['field'], 'like', "%{$search}%");
                        }
                    }
                });
            }

            // =================================================================
            // Laravel Purity: Advanced column filtering & multi-sorting
            // =================================================================
            // Purity reads directly from $request->query() using 'filters' and 'sort' keys
            // Frontend sends properly formatted params via qs.stringify()
            try {
                if ($request->hasAny(['filters', 'filter'])) {
                    $query->filter();
                }
                if ($request->hasAny(['sorts', 'sort'])) {
                    $query->sort();
                }
            } catch (\Exception $e) {
                Log::warning('Laravel Purity failed to apply filter/sort', [
                    'model'   => get_class($this),
                    'message' => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);
            }

            // =================================================================
            // Custom Modifiers (e.g., permission scopes, status=active, etc.)
            // =================================================================
            foreach ($customModifiers as $modifier) {
                if (is_callable($modifier)) {
                    $modifier($query, $request);
                } else {
                    Log::warning('Invalid custom modifier provided for ' . get_class($this));
                }
            }

            // =================================================================
            // Data Fetching Modes
            // =================================================================

            // 1. Full Load – Used when total ≤ clientSideThreshold (instant client-side UX)
            if ($isFullLoad) {
                $data = $query->get();

                return [
                    'data'         => $data,
                    'totalRecords' => $data->count(),
                    'columns'      => $columns,
                ];
            }

            // 2. Window Mode – Large datasets: prefetch larger chunks for smooth scrolling
            if ($isWindow) {
                $windowSize = min((int) $perPage, config('tables.max_window_size', 500)); // Safety cap

                $data = $query
                    ->skip(($page - 1) * $windowSize)
                    ->take($windowSize)
                    ->get();

                // Use separate count query to avoid loading all data twice
                $total = $query->toBase()->getCountForPagination();

                return [
                    'data'         => $data,
                    'totalRecords' => $total,
                    'columns'      => $columns,
                ];
            }

            // 3. Standard Pagination – Default mode
            $perPageValue = is_numeric($perPage)
                ? min(max((int) $perPage, 1), config('tables.max_per_page', 100))
                : config('tables.default_per_page', 20);

            $paginator = $query->paginate($perPageValue)->appends($request->query());

            // Extract list of fields used in global search for frontend optimization
            $globalFilterableFields = collect($columns)
                ->where('filterable', true)
                ->where('filterType', 'text')
                ->pluck('field')
                ->toArray();

            return [
                'data'              => $paginator->items(),
                'totalRecords'      => $paginator->total(),
                'currentPage'       => $paginator->currentPage(),
                'lastPage'          => $paginator->lastPage(),
                'perPage'           => $paginator->perPage(),
                'columns'           => $columns,
                'globalFilterables' => $globalFilterableFields, // ← Added for frontend
            ];

        } catch (\Exception $e) {
            // Comprehensive error logging
            Log::error('Table query failed for model: ' . get_class($this), [
                'message'  => $e->getMessage(),
                'request'  => $request->all(),
                'trace'    => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to let controller handle (e.g., return error response)
        }
    }
}