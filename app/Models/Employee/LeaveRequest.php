<?php

namespace App\Models\Employee;

use App\Models\Configuration\LeaveType;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasConfig;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LeaveRequest extends Model
{
    /** @use HasFactory<\Database\Factories\Employee\LeaveRequestFactory> */
    use HasFactory, LogsActivity, BelongsToSchool;

    protected $fillable = [
        'staff_id',
        'leave_type_id',
        'reason',
        'start_date',
        'end_date',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejected_reason',
        'school_id'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Get the leaveType that owns the LeaveRequest
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(Staff::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(Staff::class, 'rejected_by');
    }

    public function getActivitylogOptions(): LogOptions {
        return LogOptions::defaults()
            ->logOnly(['*'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
