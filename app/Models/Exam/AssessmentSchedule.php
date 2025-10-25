<?php

namespace App\Models\Exam;

use App\Models\Academic\ClassSection;
use App\Models\Academic\Subject;
use App\Models\Employee\Staff;
use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing an assessment schedule in the school management system.
 *
 * @property int $id Primary key
 * @property string $assessment_id UUID of the associated assessment
 * @property string $school_id UUID of the associated school
 * @property string $subject_id UUID of the associated subject
 * @property string $class_section_id ID of the associated class section
 * @property string $invigilator_id UUID of the associated staff (invigilator)
 * @property \Illuminate\Support\Carbon $start_date Start date of the assessment
 * @property \Illuminate\Support\Carbon $end_date End date of the assessment
 * @property string $start_time Start time of the assessment
 * @property string $end_time End time of the assessment
 * @property string $status Assessment schedule status (e.g., draft, active, completed)
 * @property string|null $venue Assessment venue
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class AssessmentSchedule extends Model
{
    use BelongsToSchool, HasTableQuery, LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'assessment_schedules';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'assessment_id',
        'school_id',
        'subject_id',
        'class_section_id',
        'invigilator_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'status',
        'venue',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    /**
     * The attributes used for global filtering.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = ['venue', 'status'];

    /**
     * The attributes hidden from table queries.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = ['school_id', 'created_at', 'updated_at'];

    /**
     * Define the relationship with the Assessment model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * Define the relationship with the Subject model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Define the relationship with the ClassSection model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classSection()
    {
        return $this->belongsTo(ClassSection::class);
    }

    /**
     * Define the relationship with the Staff model (invigilator).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function invigilator()
    {
        return $this->belongsTo(Staff::class, 'invigilator_id');
    }

    /**
     * Get the activity log options for the model.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->useLogName('assessment_schedule');
    }
}
