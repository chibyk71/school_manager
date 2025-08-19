<?php

namespace App\Models\Exam;

use App\Models\Academic\Term;
use App\Models\Model;
use App\Models\SchoolSection;
use App\Traits\BelongsToSchool;
use App\Traits\BelongsToSections;
use App\Traits\HasConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Assessment extends Model
{
    use LogsActivity, BelongsToSchool, BelongsToSections, HasUuids;

    protected $fillable = [
        'assessment_type_id',
        'school_id',
        'name',
        'term_id',
        'date_effective', // start date, when students can start taking the exam
        'date_due', // end date, when students can no longer take the exam
        'date_published', // if result is ready to be viewed by students
        'instruction'
    ];

    protected $appends = [
        'type'
    ];

    public function getTypeAttribute()
    {
        return $this->assessmentType->name;
    }

    public function assessmentType()
    {
        return $this->belongsTo(AssessmentType::class);
    }

    public function getActivityLogOptions() {
        return LogOptions::defaults()
        ->useLogName($this->configs()->name)
        ->logAll()
        ->logExcept(['updated_at'])
        ->logOnlyDirty();
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

}
