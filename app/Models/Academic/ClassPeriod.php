<?php

namespace App\Models\Academic;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClassPeriod extends Model
{
    /** @use HasFactory<\Database\Factories\Academic\ClassPeriodFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'school_id',
        'order',
        'duration',
        'is_break'
    ];

    protected $casts = [
        'is_break' => 'boolean'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('class_timing')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }

    /**
     * Get the school that owns the ClassPeriod
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
