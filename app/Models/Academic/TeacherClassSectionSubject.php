<?php

namespace App\Models\Academic;

use App\Models\Employee\Staff;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * TeacherClassSectionSubject pivot model representing a teaching assignment linking a teacher, class section, and subject.
 *
 * @property int $id
 * @property int $school_id
 * @property int $teacher_id
 * @property int $class_section_id
 * @property string $subject_id
 * @property string|null $role
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class TeacherClassSectionSubject extends Pivot
{
    use LogsActivity, BelongsToSchool, HasTableQuery;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'teacher_class_section_subjects';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'teacher_id',
        'class_section_id',
        'subject_id',
        'role',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = ['role'];

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
        'role',
    ];

    /**
     * Configure activity logging options.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('teacher_assignment')
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Teacher assignment for subject {$this->subject->code} in class section {$this->classSection->name} was {$eventName}");
    }

    /**
     * Get the role attribute from the configs or default to 'Subject Teacher'.
     *
     * @return string
     */
    public function getRoleAttribute(): string
    {
        return $this->configs()->latest()->first()?->value ?? 'Subject Teacher';
    }

    /**
     * Define the relationship to the Staff (Teacher) model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function teacher()
    {
        return $this->belongsTo(Staff::class, 'teacher_id');
    }

    /**
     * Define the relationship to the ClassSection model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classSection()
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    /**
     * Define the relationship to the Subject model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}
