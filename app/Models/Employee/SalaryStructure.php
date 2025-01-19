<?php

namespace App\Models\Employee;

use App\Traits\HasConfig;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SalaryStructure extends Model
{
    /** @use HasFactory<\Database\Factories\Employee\SalaryStructureFactory> */
    use HasFactory, LogsActivity, HasConfig, SoftDeletes;

    protected $fillable = [
        'salary_id',
        'amount',
        'currency',
        'effective_date',
        'name',
        'description',
        'school_id',
    ];

    protected $appends = [
        'salary_type'
    ];

    public function getSalaryTypeAttribute() {
        return $this->configs();
    }

    public function salary() {
        return $this->belongsTo(Salary::class);
    }

    public function getActivityLogOptions() {
        return LogOptions::defaults()
            ->logOnly([
                'salary_id',
                'salary_amount',
                'effective_date',
                'name',
                'description',
            ])
            ->logOnlyDirty();
    }
}
