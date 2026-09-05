<?php

namespace App\Exports\Lifecycle;

use App\Models\School;
use App\Services\Student\LifecycleOperationalService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Summary export for the lifecycle funnel (not a row-level table).
 */
class FunnelExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        protected School $school,
        protected array $filters = []
    ) {}

    public function title(): string
    {
        return 'Lifecycle Funnel';
    }

    public function headings(): array
    {
        return ['Stage', 'Count'];
    }

    public function array(): array
    {
        $sessionId = $this->filters['academic_session_id'] ?? null;
        $funnel = app(LifecycleOperationalService::class)
            ->lifecycleFunnel($this->school, $sessionId);

        $rows = [];
        $labels = [
            'applications' => 'Applications',
            'applications_approved' => 'Applications approved',
            'admissions' => 'Admissions',
            'admissions_accepted' => 'Admissions accepted',
            'enrollments' => 'Enrollments',
            'enrollments_finalized' => 'Enrollments finalized',
        ];

        foreach ($labels as $key => $label) {
            if (array_key_exists($key, $funnel)) {
                $rows[] = [$label, (int) $funnel[$key]];
            }
        }

        foreach ($funnel as $key => $value) {
            if (is_numeric($value) && ! isset($labels[$key])) {
                $rows[] = [str_replace('_', ' ', ucfirst($key)), (int) $value];
            }
        }

        return $rows;
    }
}
