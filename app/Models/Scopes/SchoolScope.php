<?php

namespace App\Models\Scopes;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

class SchoolScope implements Scope
{
    protected string|array $groupByColumns;

    public function __construct(string|array $groupByColumns = 'name')
    {
        $this->groupByColumns = (array) $groupByColumns;
    }

    public function apply(Builder $builder, Model $model)
    {
        $schoolId = GetSchoolModel()?->id;
        if (!$schoolId) {
            return;
        }

        $table = $model->getTable();
        $schoolIdColumn = $model::getSchoolIdColumn();  // e.g., 'school_id' or 'entity_id'
        $groupByColumns = 'name';

        // Check if 'sort' column exists in the table
        $hasSort = Schema::hasColumn($table, 'sort');

        // Dynamically build ORDER BY clause for ROW_NUMBER()
        $orderByForRowNumber = "({$schoolIdColumn} IS NOT NULL AND {$schoolIdColumn} = ?) DESC";
        if ($hasSort) {
            $orderByForRowNumber .= ", sort ASC";
        }
        $orderByForRowNumber .= ", id ASC";

        $builder->fromSub(function ($subQuery) use ($schoolId, $table, $schoolIdColumn, $groupByColumns, $orderByForRowNumber) {
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
        ", [$schoolId, $schoolId])
                ->from($table)
                ->where(function ($query) use ($schoolId, $schoolIdColumn) {
                    $query->whereNull($schoolIdColumn)
                        ->orWhere($schoolIdColumn, $schoolId);
                });
        }, "{$table}_fallback")
            ->where('row_num', 1)
            ->orderBy('priority');

        // Apply outer sort only if column exists
        if ($hasSort) {
            $builder->orderBy('sort');
        }

        $builder->orderBy('id');
    }


    public function extend(Builder $builder)
    {
        $builder->macro('withoutFallback', function (Builder $builder) {
            return $builder->withoutGlobalScope(static::class);
        });
    }
}
