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
        'term_id',
        'day',
        'start_time',
        'end_time',
        'subject_id',
        'teacher_id',
        'room_id',
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
