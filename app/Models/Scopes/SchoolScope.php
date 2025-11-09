<?php
// app/Models/Scopes/SchoolScope.php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Cache;

/**
 * SchoolScope – Tenant-aware global scope with fallback to global rows (school_id = null).
 *
 * What it does
 * ------------
 * 1. When a **school is active**:
 *     • Returns the tenant-specific row (`school_id = X`).
 *     • Falls back to a global row (`school_id IS NULL`) if none exists.
 *     • Guarantees **one row per group** (e.g. per `name`) via `ROW_NUMBER()`.
 * 2. When **no school is active** (seeders, console, etc.):
 *     • **Removes itself** – the query runs on the real table, no sub-query.
 *
 * Compatibility
 * -------------
 * • SoftDeletes – works with `withTrashed()`, `onlyTrashed()`, `restore()`.
 * • UUIDs, relationships, other global scopes – unchanged.
 * • `withoutFallback()` macro – disables the scope completely.
 */
class SchoolScope implements Scope
{
    /** @var array<string> Columns used in the PARTITION BY clause (default: ['name']) */
    protected array $groupByColumns;

    /** @var array<string, bool> Cache for "has sort column?" checks */
    private static array $hasSortCache = [];

    /**
     * @param string|array $groupByColumns Columns to partition by.
     */
    public function __construct(string|array $groupByColumns = 'name')
    {
        $this->groupByColumns = (array) $groupByColumns;
    }

    /**
     * Apply the scope.
     *
     * @param EloquentBuilder $builder The Eloquent query builder.
     * @param Model          $model   The model instance.
     */
    public function apply(EloquentBuilder $builder, Model $model): void
    {
        // -----------------------------------------------------------------
        // 1. No active school → bypass the entire fallback logic.
        // -----------------------------------------------------------------
        $school = GetSchoolModel();
        if (! $school) {
            $builder->withoutGlobalScope($this);
            return;
        }

        // -----------------------------------------------------------------
        // 2. Resolve table name & school_id column.
        // -----------------------------------------------------------------
        $table          = $model->getTable();
        $schoolIdColumn = method_exists($model, 'getSchoolIdColumn')
            ? $model->getSchoolIdColumn()
            : 'school_id';

        // -----------------------------------------------------------------
        // 3. Build PARTITION BY clause.
        // -----------------------------------------------------------------
        $groupByColumns = implode(
            ', ',
            array_map(fn($col) => $model->qualifyColumn($col), $this->groupByColumns)
        );

        // -----------------------------------------------------------------
        // 4. Optional secondary ordering by a `sort` column (cached).
        // -----------------------------------------------------------------
        $hasSort = $this->hasSortColumn($model, $table);

        // -----------------------------------------------------------------
        // 5. ORDER BY for ROW_NUMBER(): tenant > global > sort > id.
        // -----------------------------------------------------------------
        $orderBy = "({$schoolIdColumn} IS NOT NULL AND {$schoolIdColumn} = ?) DESC";
        if ($hasSort) {
            $orderBy .= ", sort ASC";
        }
        $orderBy .= ", id ASC";

        // -----------------------------------------------------------------
        // 6. Build the sub-query – alias it **directly** to the original table.
        // -----------------------------------------------------------------
        $builder->fromSub(function (QueryBuilder $sub) use (
            $model,
            $school,
            $table,
            $schoolIdColumn,
            $groupByColumns,
            $orderBy
        ) {
            $sub->from($table)
                ->where(fn($q) =>
                    $q->whereNull($schoolIdColumn)
                      ->orWhere($schoolIdColumn, $school->id)
                )
                ->selectRaw(
                    " *,
                      CASE
                        WHEN {$schoolIdColumn} = ? THEN 1
                        WHEN {$schoolIdColumn} IS NULL THEN 2
                        ELSE 3
                      END AS priority,
                      ROW_NUMBER() OVER (
                        PARTITION BY {$groupByColumns}
                        ORDER BY {$orderBy}
                      ) AS row_num ",
                    [$school->id, $school->id]
                );
        }, $table) // <-- **NO _fallback** – use the real table name

        // -----------------------------------------------------------------
        // 7. Keep only the highest-priority row per group.
        // -----------------------------------------------------------------
        ->where('row_num', 1)
        ->orderBy('priority');

        // -----------------------------------------------------------------
        // 8. Preserve optional `sort` ordering.
        // -----------------------------------------------------------------
        if ($hasSort) {
            $builder->orderBy('sort');
        }

        // -----------------------------------------------------------------
        // 9. Final stable ordering.
        // -----------------------------------------------------------------
        $builder->orderBy('id');
    }

    /**
     * Extend the builder with a `withoutFallback()` macro.
     *
     * @param EloquentBuilder $builder
     */
    public function extend(EloquentBuilder $builder): void
    {
        $builder->macro('withoutFallback', fn() => $builder->withoutGlobalScope($this));
    }

    /**
     * Cached check for a `sort` column.
     *
     * @param Model  $model
     * @param string $table
     * @return bool
     */
    private function hasSortColumn(Model $model, string $table): bool
    {
        $key = $model->getConnectionName() . '.' . $table;

        return self::$hasSortCache[$key] ??= $model
            ->getConnection()
            ->getSchemaBuilder()
            ->hasColumn($table, 'sort');
    }
}
