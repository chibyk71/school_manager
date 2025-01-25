<?php

namespace App\Models\Exam;

use App\Models\Academic\Grade;
use App\Models\Tenant\Subject;
use Illuminate\Database\Eloquent\Model;

class TermResultDetail extends Model
{
    protected $fillable = [
        'term_result_id',
        'total_score',
        'subject_id',
        'average_score',
        'position',
        'class_teacher_remark',
        'head_teacher_remark',
        'grade_id'
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function termResult()
    {
        return $this->belongsTo(TermResult::class);
    }
}
