<?php

namespace App\Exports\Lifecycle;

use App\Models\School;
use App\Services\Student\LifecycleOperationalService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AdmissionsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        protected School $school,
        protected array $filters = []
    ) {}

    public function query(): Builder
    {
        return app(LifecycleOperationalService::class)
            ->admissionsQuery($this->school, $this->filters)
            ->select([
                'id',
                'admission_number',
                'application_id',
                'academic_session_id',
                'class_level_id',
                'status',
                'acceptance_deadline',
                'registration_ends_at',
                'offered_at',
                'accepted_at',
                'created_at',
            ]);
    }

    public function headings(): array
    {
        return [
            'Admission Number',
            'Application ID',
            'Academic Session',
            'Class Level',
            'Status',
            'Origin',
            'Acceptance Deadline',
            'Registration Ends At',
            'Offered At',
            'Accepted At',
        ];
    }

    /**
     * @param  \App\Models\Student\Admission  $row
     */
    public function map($row): array
    {
        return [
            $row->admission_number,
            $row->application_id,
            $row->academic_session_id,
            $row->class_level_id,
            $row->status,
            $row->application_id ? 'application' : 'direct',
            optional($row->acceptance_deadline)->toDateTimeString(),
            optional($row->registration_ends_at)->toDateTimeString(),
            optional($row->offered_at)->toDateTimeString(),
            optional($row->accepted_at)->toDateTimeString(),
        ];
    }
}
