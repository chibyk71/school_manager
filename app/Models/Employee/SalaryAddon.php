<?php

namespace App\Models\Employee;

use App\Traits\HasConfig;
use Illuminate\Database\Eloquent\Factories\HasFactory;use App\Models\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
/**
 * this model is used to add custom salary to employees different from what is defined in their salary structure
 *  it is used to add bonuses, allowances, overtime, deductions, etc.
 *
 */
class SalaryAddon extends Model
{
    /** @use HasFactory<\Database\Factories\Employee\SalaryAddonFactory> */
    use HasFactory, HasConfig, LogsActivity;

    protected $fillable = [
        'name',
        'staff_id',
        'amount',
        'description',
        'effective_date',
        'recurrence',
        'recurrence_end_date',
    ];

    protected $appends = ['type'];

    public function getTypeAttribute()
    {
        return $this->configs();
    }

    protected $casts = [
        'effective_date' => 'date',
        'recurrence_end_date' => 'date',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function getActivityLogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['amount', 'description', 'effective_date', 'recurrence', 'recurrence_end_date'])
            ->logOnlyDirty();
    }

}
