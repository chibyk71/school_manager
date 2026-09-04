<?php

namespace App\Exports\Lifecycle;

use App\Models\School;
use App\Models\Student\Enrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Placement export uses the student_placements table when present;
 * falls back to finalized enrollments as a placement proxy.
 */
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
        if (Schema::hasTable('student_placements') && class_exists(\App\Models\Student\StudentPlacement::class)) {
            $q = \App\Models\Student\StudentPlacement::query()->where('school_id', $this->school->id);
            if (! empty($this->filters['academic_session_id'])) {
                $q->where('academic_session_id', $this->filters['academic_session_id']);
            }

            return $q->orderByDesc('created_at');
        }

        $q = Enrollment::query()
            ->where('school_id', $this->school->id)
            ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_COMPLETED]);
        if (! empty($this->filters['academic_session_id'])) {
            $q->where('academic_session_id', $this->filters['academic_session_id']);
        }

        return $q->orderByDesc('activated_at');
    }

    public function headings(): array
    {
        return [
            'Record ID',
            'Student ID',
            'Academic Session',
            'Status',
            'Activated At',
            'Created At',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->student_id ?? null,
            $row->academic_session_id ?? null,
            $row->status ?? null,
            optional($row->activated_at ?? null)->toDateTimeString(),
            optional($row->created_at ?? null)->toDateTimeString(),
        ];
    }
}
