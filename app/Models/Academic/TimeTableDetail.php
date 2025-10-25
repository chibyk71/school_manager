<?php

namespace App\Models\Academic;

use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * TimeTableDetail model representing a specific lesson slot in a timetable.
 *
 * @property int $id
 * @property int $school_id
 * @property string $timetable_id
 * @property int $class_period_id
 * @property int $teacher_class_section_subject_id
 * @property string $day
 * @property string $start_time
 * @property string $end_time
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class TimeTableDetail extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, BelongsToSchool, HasTableQuery;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'time_table_details';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'timetable_id',
        'class_period_id',
        'teacher_class_section_subject_id',
        'day',
        'start_time',
        'end_time',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_time' => 'string',
        'end_time' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'school_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'day',
    ];

    /**
     * Configure activity logging options.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('timetable_detail')
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Timetable detail for {$this->day} was {$eventName}");
    }

    /**
     * Define the relationship to the ClassPeriod model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classPeriod()
    {
        return $this->belongsTo(ClassPeriod::class, 'class_period_id');
    }

    /**
     * Define the relationship to the TimeTable model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function timetable()
    {
        return $this->belongsTo(TimeTable::class, 'timetable_id');
    }

    /**
     * Define the relationship to the TeacherClassSectionSubject model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function teacherClassSectionSubject()
    {
        return $this->belongsTo(TeacherClassSectionSubject::class, 'teacher_class_section_subject_id');
    }

    /**
     * Scope a query to only include timetable details for a specific timetable.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $timetableId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTimetable($query, string $timetableId)
    {
        return $query->where('timetable_id', $timetableId);
    }
}