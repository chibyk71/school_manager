<?php

namespace App\Models\Employee;

use App\Models\Academic\AcademicSession;
use App\Models\Configuration\LeaveType;
use App\Models\Model;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;

class LeaveAllocation extends Model
{
    /** @use HasFactory<\Database\Factories\Employee\LeaveAllocationFactory> */
    use HasFactory, LogsActivity, BelongsToSchool;

    protected $fillable = [
        'leave_type_id',
        'no_of_days',
        'academic_session_id',
        'school_id'
    ];

    public function getActivityLogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logOnly([
                'leave_type_id',
                'no_of_days',
                'academic_session_id',
                'school_id'
            ])
            ->logOnlyDirty();
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }
}
