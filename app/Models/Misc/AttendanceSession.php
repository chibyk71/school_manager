<?php

namespace App\Models\Misc;

use App\Models\Model;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasConfig;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AttendanceSession extends Model
{
    use HasConfig, LogsActivity, BelongsToSchool;
    protected $fillable = [
        'class_section_id',
        'date_effective',
        'class_period_id',
        'shool_id',
        'name',
        'description',
        'manager',
    ];

    protected static $logName = 'attendance_session';

    protected $appends = ['type'];

    public function getTypeAttribute()
    {
        return $this->configs();
    }

    public function getActivityLogOptions()
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'manager', 'date_effective', 'class_section_id', 'class_period_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
    public function classSection()
    {
        return $this->belongsTo('App\Models\ClassSection');
    }

    public function classPeriod()
    {
        return $this->belongsTo('App\Models\ClassPeriod');
    }

    /**
     * Get the manager that owns the AttendanceSession
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager', 'id');
    }


}
