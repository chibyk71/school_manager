<?php

namespace App\Metrics;

use App\Models\Academic\Student;
use Illuminate\Database\Eloquent\Builder;

class StudentMetric extends AbstractMetric
{
    protected string $model = Student::class;

    /* ------------------------------------------------------------------ */
    /* PUBLIC API – used directly by the dashboard controller             */
    /* ------------------------------------------------------------------ */

    /** Total students (any filters) */
    public function total(array $filters = []): array
    {
        return $this->value('count', null, $filters);
    }

    /** Active students */
    public function active(): array
    {
        return $this->value('count', null, ['status' => 'active']);
    }

    /** Inactive students */
    public function inactive(): array
    {
        return $this->value('count', null, ['status' => 'inactive']);
    }

    /** Gender breakdown (Doughnut) */
    public function genderBreakdown(): array
    {
        return $this->breakdown('gender');
    }

    /** Enrollment trend (YTD) */
    public function enrollmentTrendYTD(): array
    {
        return $this->trend('monthly');
    }

    /* ------------------------------------------------------------------ */
    /* BASE QUERY – automatically scopes to current session               */
    /* ------------------------------------------------------------------ */
    protected function buildBaseQuery(array $filters): Builder
    {
        $query = $this->model::query();
        // ->where('session_id', currentSession()?->id);   // <-- your helper

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['class_id'])) {
            $query->whereIn('class_id', (array) $filters['class_id']);
        }

        return $query;
    }

    protected function getTitle(): string
    {
        return 'Total Students';
    }
    protected function getImage(): string
    {
        return '/assets/img/icons/student.svg';
    }
}
