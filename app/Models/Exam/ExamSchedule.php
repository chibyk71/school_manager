<?php

namespace App\Models\Exam;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\Subject;
use App\Models\Model;
use App\Traits\BelongsToSchool;

class ExamSchedule extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id',
        'academic_session_id',
        'class_level_id',
        'subject_id',
        'exam_date',
        'start_time',
        'end_time',
        'venue',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function classLevel()
    {
        return $this->belongsTo(ClassLevel::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
