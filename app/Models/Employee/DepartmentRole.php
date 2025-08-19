<?php

namespace App\Models\Employee;

use App\Models\Model;
use App\Models\SchoolSection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class DepartmentRole extends Model
{
    /** @use HasFactory<\Database\Factories\Employee\DepartmentRoleFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'department_id',
        'school_section_id',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function schoolSection()
    {
        return $this->belongsTo(SchoolSection::class);
    }

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logOnly(['name', 'department_id', 'school_section_id'])
            ->useLogName('Department Role');
    }
}
