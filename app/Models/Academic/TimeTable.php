<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TimeTable extends Model
{
    /** @use HasFactory<\Database\Factories\Academic\TimeTableFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'title',
        "timeable_type_id",
        'term_id',
        'effective_date',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('class_timing')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
