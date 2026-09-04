<?php

namespace App\Exports\Lifecycle;

use App\Models\School;
use App\Services\Student\LifecycleOperationalService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ApplicationsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
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
            ->applicationsQuery($this->school, $this->filters)
            ->select([
                'id',
                'application_number',
                'first_name',
                'middle_name',
                'last_name',
                'email',
                'academic_session_id',
                'class_level_id',
                'status',
                'source',
                'fee_payment_status',
                'submitted_at',
                'created_at',
            ]);
    }

    public function headings(): array
    {
        return [
            'Application Number',
            'Candidate',
            'Email',
            'Academic Session',
            'Class Level',
            'Status',
            'Source',
            'Fee Status',
            'Submitted At',
        ];
    }

    /**
     * @param  \App\Models\Student\StudentApplication  $row
     */
    public function map($row): array
    {
        $name = trim(implode(' ', array_filter([
            $row->first_name,
            $row->middle_name,
            $row->last_name,
        ])));

        return [
            $row->application_number,
            $name,
            $row->email,
            $row->academic_session_id,
            $row->class_level_id,
            $row->status,
            $row->source ?? null,
            $row->fee_payment_status ?? null,
            optional($row->submitted_at)->toDateTimeString() ?? optional($row->created_at)->toDateTimeString(),
        ];
    }
}
