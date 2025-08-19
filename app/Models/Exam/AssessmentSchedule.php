<?php

namespace App\Models\Exam;

use App\Models\Academic\ClassSection;
use App\Models\Academic\Subject;
use App\Models\Employee\Staff;
use App\Models\Model;

class AssessmentSchedule extends Model
{
    protected $table = 'assessment_schedules';

    protected $fillable = [
        'assessment_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'status',
        'subject_id',
        'class_section_id',
        'invigilator_id',
        'venue'
    ];

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function classSection()
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function invigilator()
    {
        return $this->belongsTo(Staff::class);
    }
}
