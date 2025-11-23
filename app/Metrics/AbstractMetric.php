<?php

namespace App\Metrics;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

abstract class AbstractMetric
{
    protected string $model;
    protected ?int $cacheMinutes = 10;

    /** @return void */
    protected function authorize(): void
    { /* optional gate */
    }

    /** @return string */
    protected function cacheKey(string $suffix, array $params = []): string
    {
        $hash = !empty($params) ? md5(json_encode($params)) : '';
        return "metrics.{$this->model}.{$suffix}.{$hash}";
    }

    /** @return string */
    protected function getTitle(): string
    {
        return class_basename($this->model);
    }

    /** @return string */
    protected function getImage(): string
    {
        return '/assets/img/icons/default.svg';
    }

    /** @return string */
    protected function getSeverity(): string
    {
        return 'bg-blue-200/50';
    }

    /* ------------------------------------------------------------------ */
    /* Helper wrappers – keep DRY across all concrete metrics             */
    /* ------------------------------------------------------------------ */

    /** @return array{value:mixed,title:string,image:string,severity:string,growth?:float} */
    protected function value(string $type, ?string $column = null, array $filters = [], bool $withGrowth = true, int $rangeDays = 30): array
    {
        // Build base query (your custom scopes + filters)
        $base = $this->baseQueryForMetrics($filters);
        $sub  = $base->getQuery();

        // CRITICAL FIX:
        // When using fromSub(), Laravel's SoftDeletingScope adds `students.deleted_at is null`
        // but it qualifies it with the original table name (`students`), not the alias.
        // If we alias as 't', it becomes `students.deleted_at` → column not found.
        // Solution: Use the REAL table name as alias so the qualified column matches.
        $modelInstance = new $this->model;
        $tableName     = $modelInstance->getTable();

        // Wrap the subquery and alias it with the actual table name (e.g. "students")
        $wrapped = DB::table(DB::raw("({$sub->toSql()}) as `{$tableName}`"))
            ->mergeBindings($sub);

        $metric = \SaKanjo\EasyMetrics\Metrics\Value::make($this->model)
            // Use the real table name as subquery alias
            ->modifyQuery(fn($q) => $q->fromSub($wrapped, $tableName))
            ->range($rangeDays);

        if ($withGrowth) {
            $metric->withGrowthRate();
        }

        $result = match ($type) {
            'count' => $metric->count(),
            'sum'   => $metric->sum($column),
            'avg'   => $metric->average($column),
            default => throw new \InvalidArgumentException("Unsupported type: $type"),
        };

        return [
            'value'    => $result->getValue() ?? 0,
            'growth'   => $result->getGrowthRate() ?? 0,
            'title'    => $this->getTitle(),
            'image'    => $this->getImage(),
            'severity' => $this->getSeverity(),
        ];
    }

    /** @return array{labels:array<string>,data:array<mixed>} */
    protected function breakdown(string $groupBy, array $filters = [], string $type = 'count'): array
    {
        $base = $this->baseQueryForMetrics($filters);
        $sub  = $base->getQuery();

        $modelInstance = new $this->model;
        $tableName     = $modelInstance->getTable();

        $wrapped = DB::table(DB::raw("({$sub->toSql()}) as `{$tableName}`"))
            ->mergeBindings($sub);

        $metric = \SaKanjo\EasyMetrics\Metrics\Doughnut::make($this->model)
            ->modifyQuery(fn($q) => $q->fromSub($wrapped, $tableName));

        $result = match ($type) {
            'count' => $metric->count($groupBy),
            default => throw new \InvalidArgumentException("Unsupported breakdown type: $type"),
        };

        [$labels, $data] = $result;
        return compact('labels', 'data');
    }

    /** @return array{labels:array<string>,data:array<mixed>,growth?:float} */
    protected function trend(string $interval = 'monthly', \SaKanjo\EasyMetrics\Enums\Range $range = \SaKanjo\EasyMetrics\Enums\Range::YTD, array $filters = []): array
    {
        $base = $this->baseQueryForMetrics($filters);
        $sub  = $base->getQuery();

        $modelInstance = new $this->model;
        $tableName     = $modelInstance->getTable();

        $wrapped = DB::table(DB::raw("({$sub->toSql()}) as `{$tableName}`"))
            ->mergeBindings($sub);

        $metric = \SaKanjo\EasyMetrics\Metrics\Trend::make($this->model)
            ->modifyQuery(fn($q) => $q->fromSub($wrapped, $tableName))
            ->range($range)
            ->withGrowthRate();

        $result = match (strtolower($interval)) {
            'monthly' => $metric->countByMonths(),
            'daily'   => $metric->countByDays(),
            default   => throw new \InvalidArgumentException("Unsupported interval: $interval"),
        };

        [$labels, $data] = $result;
        $growth = $result->getGrowthRate() ?? 0;

        return compact('labels', 'data', 'growth');
    }

    /** @return \Illuminate\Database\Eloquent\Builder */
    abstract protected function buildBaseQuery(array $filters): \Illuminate\Database\Eloquent\Builder;

    protected function baseQueryForMetrics(array $filters = []): \Illuminate\Database\Eloquent\Builder
    {
        return $this->buildBaseQuery($filters)
            ->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class);
    }
}
