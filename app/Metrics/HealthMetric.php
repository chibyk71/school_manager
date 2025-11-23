<?php

namespace App\Metrics;

use App\Models\Health\MedicalAlert;
use Illuminate\Database\Eloquent\Builder;

class HealthMetric extends AbstractMetric
{
    protected string $model = MedicalAlert::class;

    /* ------------------------------------------------------------------ */
    /* PUBLIC API                                                         */
    /* ------------------------------------------------------------------ */

    /** Medical alerts today */
    public function alertsToday(): array
    {
        $count = MedicalAlert::whereDate('created_at', today())->count();

        return [
            'value' => $count,
            'title' => 'Medical Alerts Today',
            'image' => '/assets/img/icons/health.svg',
            'severity' => $count > 0 ? 'bg-red-200/50' : 'bg-green-200/50',
        ];
    }

    /** Incident trend (last 30 days) */
    public function incidentTrend(): array
    {
        return $this->trend('daily', \SaKanjo\EasyMetrics\Enums\Range::MTD);
    }

    /** Incident type doughnut */
    public function typeBreakdown(): array
    {
        return $this->breakdown('type');
    }

    /* ------------------------------------------------------------------ */
    /* BASE QUERY                                                         */
    /* ------------------------------------------------------------------ */
    protected function buildBaseQuery(array $filters): Builder
    {
        $query = $this->model::query();

        if (!empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        return $query;
    }

    protected function getTitle(): string { return 'Health & Incidents'; }
}
