<?php

namespace App\Models\Resource;

use FarhanShares\MediaMan\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class LessonPlanDetail extends Model
{
    use LogsActivity, HasMedia;

    protected $table = 'lesson_plan_details';

    protected $fillable = [
        'lesson_plan_id',
        'title',
        'sub_title',
        'objective',
        'activity', // This should be an array of activities eg ['discussion', 'group work', 'presentation']
        'teaching_method', // This should be an array of teaching methods eg ['lecture', 'discussion', 'group work']
        'evaluation', // This should be an array of evaluation methods eg ['quiz', 'assignment', 'project']
        'resources', // This should be an array of resources used in the lesson plan eg ['book', 'whiteboard', 'chalk']
        'duration', // This should be in minutes
        'remarks',
        'status' // This should be an enum of 'draft', 'published', 'archived'
    ];

    protected $casts = [
        'activity' => 'array',
        'teaching_method' => 'array',
        'evaluation' => 'array',
        'resources' => 'array'
    ];
    

    public function lessonPlan()
    {
        return $this->belongsTo(LessonPlan::class);
    }

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logOnly(['*'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Lesson Plan Detail');
    }
}
