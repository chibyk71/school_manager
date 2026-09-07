<?php

namespace App\Exports\Lifecycle;

use App\Models\School;
use App\Services\Student\LifecycleOperationalService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PlacementsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
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
            ->placementsQuery($this->school, $this->filters)
            ->with(['classLevel:id,name', 'classSection:id,name', 'academicSession:id,name', 'student:id']);
    }

    public function headings(): array
    {
        return [
            'Placement ID',
            'Student ID',
            'Enrollment ID',
            'Academic Session',
            'Class Level',
            'Section',
            'Registration Number',
            'Enrolled At',
            'Is Current',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->student_id,
            $row->enrollment_id,
            $row->academicSession->name ?? $row->academic_session_id,
            $row->classLevel->name ?? $row->class_level_id,
            $row->classSection->name ?? $row->class_section_id,
            $row->registration_number,
            optional($row->enrolled_at)->toDateString(),
            $row->is_current ? 'yes' : 'no',
        ];
    }
}
