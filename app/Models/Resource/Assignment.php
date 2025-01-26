<?php

namespace App\Models\Resource;

use App\Models\Employee\Staff;
use App\Traits\BelongsToSchool;
use App\Traits\HasConfig;
use FarhanShares\MediaMan\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Assignment extends Model
{
    use BelongsToSchool, HasMedia, HasConfig, LogsActivity;

    protected $table = 'assignments';

    protected $fillable = [
        'school_id',
        'class_level_id',
        'subject_id',
        'title',
        'description',
        'term_id',
        'total_mark',
        'due_date',
        'teacher_id'
    ];

    protected $casts = [
        'due_date' => 'datetime',
    ];

    protected $appends = [
        'media',
        'type',
    ];

    public function getTypeAttribute()
    {
        return $this->configs()->name;
    }

    public function teacher()
    {
        return $this->belongsTo(Staff::class, 'teacher_id');
    }

    public function classLevel()
    {
        return $this->belongsTo('App\Models\Academic\ClassLevel', 'class_level_id');
    }

    public function subject()
    {
        return $this->belongsTo('App\Models\Academic\Subject', 'subject_id');
    }

    public function term()
    {
        return $this->belongsTo('App\Models\Academic\Term', 'term_id');
    }

    public function getActivityLogOptions()
    {
        return LogOptions::defaults()
            ->useLogName('assignment')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
