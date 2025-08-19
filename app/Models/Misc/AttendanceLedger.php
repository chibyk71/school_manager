<?php

namespace App\Models\Misc;

use App\Models\Model;

class AttendanceLedger extends Model
{
    protected $table = 'attendance_ledger';

    protected $fillable = [
        'attendance_session_id',
        'remark',
        'status',
        'attendable_id',
        'attendable_type',
    ];

    public function attendable()
    {
        return $this->morphTo();
    }

    public function attendanceSession()
    {
        return $this->belongsTo(AttendanceSession::class);
    }
}
