<?php

namespace App\Models\Configuration;

use App\Models\Employee\LeaveAllocation;
use App\Models\Employee\LeaveRequest;use App\Models\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LeaveType extends Model
{
    use LogsActivity;

    protected $table = 'leave_types';

    protected $fillable = [
        'name',
        'description',
        'max_days',
        'options',
        'school_id'
    ];

    public function school()
    {
        return $this->belongsTo('App\Models\School');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveAllocations() {
        return $this->hasMany(LeaveAllocation::class);
    }

    public function getActivityLogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'max_days', 'options', 'school_id'])
            ->logOnlyDirty();
    }

}
