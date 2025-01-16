<?php

namespace App\Models\Academic;

use App\Models\Tenant\TimeTable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TimeTableDetail extends Model
{
    /** @use HasFactory<\Database\Factories\TimeTableDetailFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'class_period_id',
        'teacher_class_section_subject_id',
        'time_table_id'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Time Table Entry')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }

    /**
     * Get the classPeriod that owns the TimeTableDetail
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classPeriod()
    {
        return $this->belongsTo(ClassPeriod::class, 'class_period_id', 'id');
    }

    /**
     * Get the TimeTable that owns the TimeTableDetail
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function timeTable()
    {
        return $this->belongsTo(TimeTable::class, 'time_table_id');
    }

    /**
     * Get the twacherClassSectionSubject that owns the TimeTableDetail
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function teacherClassSectionSubject()
    {
        return $this->belongsTo(TeacherClassSectionSubject::class, 'teacher_class_section_subject_id');
    }
}
