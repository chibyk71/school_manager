<?php

namespace App\Exports\Lifecycle;

use App\Models\School;
use App\Services\Student\LifecycleOperationalService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EnrollmentsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
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
            ->enrollmentsQuery($this->school, $this->filters)
            ->select([
                'id',
                'student_id',
                'admission_id',
                'academic_session_id',
                'status',
                'activated_at',
                'started_at',
                'completed_at',
                'created_at',
            ]);
    }

    public function headings(): array
    {
        return [
            'Enrollment ID',
            'Student ID',
            'Admission ID',
            'Academic Session',
            'Status',
            'Origin',
            'Activated At',
            'Started At',
            'Completed At',
            'Created At',
        ];
    }

    /**
     * @param  \App\Models\Student\Enrollment  $row
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->student_id,
            $row->admission_id,
            $row->academic_session_id,
            $row->status,
            $row->admission_id ? 'admission' : 'direct',
            optional($row->activated_at)->toDateTimeString(),
            optional($row->started_at)->toDateTimeString(),
            optional($row->completed_at)->toDateTimeString(),
            optional($row->created_at)->toDateTimeString(),
        ];
    }
}
