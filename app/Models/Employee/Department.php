<?php

namespace App\Models\Employee;

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
    use HasFactory, SoftDeletes, LogsActivity, BelongsToSchool;

    protected $fillable = [
        'name',
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
}
