<?php

namespace App\Models\Student;

use App\Models\Model;
use App\Traits\BelongsToSchool;
use SpykApp\LaravelCustomFields\Traits\HasCustomFields;
use SpykApp\LaravelCustomFields\Traits\LoadCustomFields;

class Admission extends Model
{
    use BelongsToSchool;

    protected $table = 'admissions';

    protected $fillable = [
        'roll_no',
        'school_id',
        'class_Level_id',
        'school_section_id',
        'academic_session_id',
        'status',
    ];
}
