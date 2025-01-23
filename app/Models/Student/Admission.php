<?php

namespace App\Models\Student;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use SpykApp\LaravelCustomFields\Traits\HasCustomFields;
use SpykApp\LaravelCustomFields\Traits\LoadCustomFields;

class Admission extends Model
{
    use BelongsToSchool, HasCustomFields, LoadCustomFields;

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
