<?php

namespace App\Models\Employee;

use App\Models\Configuration\LeaveType;
use Illuminate\Database\Eloquent\Model;

class LeaveLedger extends Model
{
    protected $table = 'leave_ledger';

    protected $fillable = [
        'staff_id',
        'leave_type_id',
        'academic_session_id',
        'encashed_days',
    ];

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
    
}
