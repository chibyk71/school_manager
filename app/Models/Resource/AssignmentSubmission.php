<?php

namespace App\Models\Resource;

use App\Models\Employee\Staff;
use App\Models\Model;
use FarhanShares\MediaMan\Traits\HasMedia;

class AssignmentSubmission extends Model
{
    use HasMedia;
    
    protected $fillable = [
        'student_id',
        'assignment_id',
        'answer_text',
        'mark_obtained',
        'status',
        'submitted_at',
        'graded_by',
        'remark',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo('App\Models\People\Student', 'student_id');
    }

    public function assignment()
    {
        return $this->belongsTo('App\Models\Resource\Assignment', 'assignment_id');
    }

    public function gradedBy()
    {
        return $this->belongsTo(Staff::class, 'graded_by');
    }

}
