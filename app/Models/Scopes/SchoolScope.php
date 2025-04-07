<?php

namespace App\Models\Scopes;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SchoolScope implements Scope
{
    protected string|array $groupByColumns;

    public function __construct(string|array $groupByColumns = 'name')
    {
        $this->groupByColumns = (array) $groupByColumns;
    }

    public function apply(Builder $builder, Model $model)
    {
        // Get the current school ID
        $schoolId = GetSchoolModel()?->id;
        if (!$schoolId) {
            return;
        }

        // Get the table name and school_id column from the model
        $table = $model->getTable();
        $schoolIdColumn = $model::getSchoolIdColumn();  // Default is usually 'school_id'

        // Ensure group by columns are properly formatted for SQL (e.g. name, category, etc.)
        $groupByColumns = 'name';  // You can extend this if you want to group by more columns

        // Apply window function logic using ROW_NUMBER() and fallback priority
        $builder->fromSub(function ($subQuery) use ($schoolId, $table, $schoolIdColumn, $groupByColumns) {
            $subQuery->selectRaw("
            *,
            CASE
                WHEN {$schoolIdColumn} = ? THEN 1
                WHEN {$schoolIdColumn} IS NULL THEN 2
                ELSE 3
            END AS priority,
            ROW_NUMBER() OVER (
                PARTITION BY {$groupByColumns}
                ORDER BY
                    ({$schoolIdColumn} IS NOT NULL AND {$schoolIdColumn} = ?) DESC,
                    sort ASC, id ASC
            ) AS row_num
        ", [$schoolId, $schoolId])  // Bind parameters for school ID
                ->from($table)
                ->where(function ($query) use ($schoolId, $schoolIdColumn) {
                    $query->whereNull($schoolIdColumn)
                        ->orWhere($schoolIdColumn, $schoolId);
                });
        }, "{$table}_fallback")  // Alias for the subquery
            ->where('row_num', 1)  // Move the filter for row_num to the outer query
            ->orderBy('priority')  // Sort by priority, custom school entries first
            ->orderBy('sort')  // Add additional ordering (e.g., by `sort` field)
            ->orderBy('id');  // Finally, by `id`
    }


    public function extend(Builder $builder)
    {
        $builder->macro('withoutFallback', function (Builder $builder) {
            return $builder->withoutGlobalScope(static::class);
        });
    }
}
