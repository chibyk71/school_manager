<?php

namespace App\Models\Resource;

use App\Traits\BelongsToSchool;
use FarhanShares\MediaMan\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class LessonPlan extends Model
{
    use BelongsToSchool, HasMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'class_level_id',
        'subject_id',
        'sylabus_detail_id',
        'school_id',
        'topic',
        'date',
        'objective',
        'material',
        'assessment',
        'staff_id'
    ];

    protected $casts = [
        'date' => 'date',
        'material' => 'array',
        'assessment' => 'array'
    ];

    public function sylabusDetail()
    {
        return $this->belongsTo('App\Models\Academic\SylabusDetail', 'sylabus_detail_id');
    }

    public function classLevel()
    {
        return $this->belongsTo('App\Models\Academic\ClassLevel', 'class_level_id');
    }

    public function subject()
    {
        return $this->belongsTo('App\Models\Academic\Subject', 'subject_id');
    }


    public function staff()
    {
        return $this->belongsTo('App\Models\Employee\Staff', 'staff_id');
    }

    public function lessonPlanDetails()
    {
        return $this->hasMany('App\Models\Resource\LessonPlanDetail', 'lesson_plan_id');
    }

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }
}
