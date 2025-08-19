<?php

namespace App\Models\Exam;

use App\Models\Academic\ClassSection;
use App\Models\Academic\Grade;
use App\Models\Academic\Student;
use App\Models\Employee\Staff;
use App\Models\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssessmentResult extends Model
{
    use LogsActivity;

    protected $table = 'assessment_results';

    protected $fillable = [
        'assessment_id',
        'student_id',
        'subject',
        'result',
        'grade_id',
        'remark',
        'class_section_id',
        'graded_by',
    ];

    public function getActivityLogOptions() {
        return LogOptions::defaults()
        ->logAll()
        ->logExcept(['updated_at'])
        ->logOnlyDirty()
        ->useLogName('Assessment Result');
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function classSection()
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function gradedBy()
    {
        return $this->belongsTo(Staff::class, 'graded_by');
    }
}
