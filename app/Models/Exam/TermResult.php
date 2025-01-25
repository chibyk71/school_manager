<?php

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Model;

class TermResult extends Model
{
    

    protected $fillable = [
        'student_id',
        'term_id',
        'class_id',
        'average_score',
        'position',
        'class_teacher_remark',
        'head_teacher_remark',
        'grade'
    ];

    public function student()
    {
        return $this->belongsTo('App\Models\Student');
    }

    public function term()
    {
        return $this->belongsTo('App\Models\Term');
    }

    public function class()
    {
        return $this->belongsTo('App\Models\Class');
    }
}
