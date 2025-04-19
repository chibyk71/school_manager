<?php

namespace App\Models\Employee;

use App\Models\Role;
use App\Models\School;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Department extends Model
{
    /** @use HasFactory<\Database\Factories\Employee\DepartmentFactory> */
    use HasFactory, LogsActivity, BelongsToSchool;

    protected $fillable = [
        'name',
        'category',
        'description',
        'effective_date',
        'school_id',
    ];

    protected $casts = [
        'effective_date' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'effective_date'])
            ->logOnlyDirty();
    }

    /**
     * The roles that belong to the Department
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'department_role', 'department_id', 'role_id');
    }
}
