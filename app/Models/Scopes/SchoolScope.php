<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Global scope to filter models by school with a fallback to global records.
 *
 * Prioritizes school-specific records (matching school_id) over global records (null school_id)
 * using a ROW_NUMBER() subquery, grouped by specified columns.
 * Provides a `withoutFallback` macro to disable the scope.
 */
class SchoolScope implements Scope
{
    /**
     * Columns to group by for fallback logic.
     *
     * @var array<string>
     */
    protected array $groupByColumns;

    /**
     * Constructor to set group by columns.
     *
     * @param string|array $groupByColumns Columns to group by (e.g., 'name').
     */
    public function __construct(string|array $groupByColumns = 'name')
    {
        $this->groupByColumns = (array) $groupByColumns;
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param Builder $builder The query builder instance.
     * @param Model $model The Eloquent model instance.
     * @return void
     * @throws \Exception If no active school is found or schema check fails.
     */
    public function apply(Builder $builder, Model $model): void
    {
        try {
            $school = GetSchoolModel();
            if (!$school) {
                Log::warning('No active school found for SchoolScope.');
                return;
            }

            $table = $model->getTable();
            $schoolIdColumn = method_exists($model, 'getSchoolIdColumn') ? $model->getSchoolIdColumn() : 'school_id';

            // Validate group by columns
            $groupByColumns = implode(',', array_map(fn($col) => $model->qualifyColumn($col), $this->groupByColumns));
            $hasSort = Schema::hasColumn($table, 'sort');

            // Build ORDER BY for ROW_NUMBER()
            $orderByForRowNumber = "({$schoolIdColumn} IS NOT NULL AND {$schoolIdColumn} = ?) DESC";
            if ($hasSort) {
                $orderByForRowNumber .= ", sort ASC";
            }
            $orderByForRowNumber .= ", id ASC";

            $builder->fromSub(function ($subQuery) use ($school, $table, $schoolIdColumn, $groupByColumns, $orderByForRowNumber) {
                $subQuery->selectRaw("
                    *,
                    CASE
                        WHEN {$schoolIdColumn} = ? THEN 1
                        WHEN {$schoolIdColumn} IS NULL THEN 2
                        ELSE 3
                    END AS priority,
                    ROW_NUMBER() OVER (
                        PARTITION BY {$groupByColumns}
                        ORDER BY {$orderByForRowNumber}
                    ) AS row_num
                ", [$school->id, $school->id])
                    ->from($table)
                    ->where(function ($query) use ($school, $schoolIdColumn) {
                        $query->whereNull($schoolIdColumn)
                            ->orWhere($schoolIdColumn, $school->id);
                    });
            }, "{$table}_fallback")
                ->where('row_num', 1)
                ->orderBy('priority');

            if ($hasSort) {
                $builder->orderBy('sort');
            }

            $builder->orderBy('id');
        } catch (\Exception $e) {
            Log::error('Failed to apply SchoolScope: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extend the query builder with additional macros.
     *
     * Adds a `withoutFallback` macro to disable this scope.
     *
     * @param Builder $builder The query builder instance.
     * @return void
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutFallback', function (Builder $builder) {
            return $builder->withoutGlobalScope(static::class);
        });
    }
}
