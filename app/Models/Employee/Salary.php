<?php

namespace App\Models\Employee;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Salary extends Model
{
    /** @use HasFactory<\Database\Factories\Employee\SalaryFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'department_role_id',
        'effective_date',
        'school_id',
        'options'
    ];

    protected $casts = [
        'options' => 'array',
        'effective_date' => 'date'
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function departmentRole()
    {
        return $this->belongsTo(DepartmentRole::class);
    }

    public function getActivityLogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['department_role_id', 'effective_date', 'school_id', 'options'])
            ->logOnlyDirty();
    }
}
