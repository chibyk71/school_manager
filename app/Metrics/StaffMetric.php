<?php

namespace App\Metrics;

use App\Models\Employee\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * StaffMetric – Fully Updated for staff_department_role pivot
 *
 * Uses your new, correct architecture:
 * Staff → staff_department_role → department_role → department
 *
 * Perfect for Nigerian school SAAS:
 * - Accurate academic/non-academic filtering
 * - Correct department breakdown (even with multiple roles per staff)
 * - No data leakage (students can't appear as staff)
 * - Supports HOD, Class Teacher, Subject Teacher roles later
 */
class StaffMetric extends AbstractMetric
{
    protected string $model = Staff::class;

    /* ------------------------------------------------------------------ */
    /* PUBLIC API – Dashboard Metrics                                     */
    /* ------------------------------------------------------------------ */

    public function total(array $filters = []): array
    {
        return $this->value('count', null, $filters);
    }

    public function academic(): array
    {
        return $this->value('count', null, ['academic' => true]);
    }

    public function nonAcademic(): array
    {
        return $this->value('count', null, ['academic' => false]);
    }

    public function departmentBreakdown(): array
    {
        return $this->breakdown('departments.name');
    }

    public function hiringTrendYTD(): array
    {
        return $this->trend('monthly');
    }

    /* ------------------------------------------------------------------ */
    /* BASE QUERY – Uses the NEW staff_department_role pivot              */
    /* ------------------------------------------------------------------ */

    /**
     * Build base query using the correct relationship chain:
     * Staff → staff_department_role → department_role → department
     *
     * This is the single source of truth for staff role assignments.
     */
    protected function buildBaseQuery(array $filters): Builder
    {
        $query = $this->model::query();

        // Academic / Non-academic filter
        if (array_key_exists('academic', $filters)) {
            $isAcademic = filter_var($filters['academic'], FILTER_VALIDATE_BOOLEAN);
            unset($filters['academic']);

            $relation = 'departmentRoles.department';

            if ($isAcademic) {
                $query->whereHas($relation, fn($q) => $q->where('category', 'academic'));
            } else {
                $query->whereDoesntHave($relation, fn($q) => $q->where('category', 'academic'))
                      ->orWhereDoesntHave('departmentRoles'); // Staff with no role = non-academic
            }
        }

        // Apply any other filters (date, status, etc.)
        foreach ($filters as $key => $value) {
            is_array($value)
                ? $query->whereIn($key, $value)
                : $query->where($key, $value);
        }

        return $query;
    }

    /* ------------------------------------------------------------------ */
    /* BREAKDOWN – Fixed for new pivot + accurate counting               */
    /* ------------------------------------------------------------------ */

    /**
     * Department breakdown using the new pivot.
     * Uses DISTINCT staff.id to avoid double-counting staff with multiple roles.
     */
    protected function breakdown(string $groupBy, array $filters = [], string $type = 'count'): array
    {
        if ($type !== 'count') {
            throw new \InvalidArgumentException("Unsupported type: $type");
        }

        $query = $this->model::query()
            ->selectRaw('departments.name as label')
            ->selectRaw('COUNT(DISTINCT staff.id) as value')
            ->join('staff_department_role as sdr', 'staff.id', '=', 'sdr.staff_id')
            ->join('department_role as dr', 'sdr.department_role_id', '=', 'dr.id')
            ->join('departments', 'dr.department_id', '=', 'departments.id')
            ->whereNull('staff.deleted_at')
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('value');

        // Apply academic filter if needed
        if (!empty($filters['academic'])) {
            $isAcademic = filter_var($filters['academic'], FILTER_VALIDATE_BOOLEAN);
            $query->where('departments.category', $isAcademic ? 'academic' : '!=', 'academic');
        }

        $results = $query->get();

        return [
            'labels' => $results->pluck('label')->filter()->values()->toArray(),
            'data'   => $results->pluck('value')->map('intval')->toArray(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* UI METADATA                                                        */
    /* ------------------------------------------------------------------ */

    protected function getTitle(): string
    {
        return 'Total Staff';
    }

    protected function getImage(): string
    {
        return '/assets/img/icons/teacher.svg';
    }

    protected function getSeverity(): string
    {
        return 'bg-blue-200/50';
    }
}
