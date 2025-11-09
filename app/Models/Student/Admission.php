<?php

namespace App\Models\Student;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasCustomFields;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class Admission
 *
 * Represents a student admission record in the school management system.
 *
 * @package App\Models\Student
 * @property string $id
 * @property string $school_id
 * @property string $student_id
 * @property string $class_level_id
 * @property string $school_section_id
 * @property string $academic_session_id
 * @property string $roll_no
 * @property string $status
 * @property array|null $configs
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Admission extends Model
{
    use BelongsToSchool, HasTableQuery, LogsActivity, SoftDeletes, HasCustomFields, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'admissions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'class_level_id',
        'school_section_id',
        'academic_session_id',
        'roll_no',
        'status',
        'configs',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'configs' => 'array',
        'status' => 'string',
    ];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected $hiddenTableColumns = [
        'created_at',
        'updated_at',
        'deleted_at',
        'configs',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected $globalFilterFields = [
        'roll_no',
        'status',
    ];

    /**
     * Define the relationship with the student.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
        return $this->belongsTo(\App\Models\Academic\Student::class);
    }

    /**
     * Define the relationship with the class level.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classLevel()
    {
        return $this->belongsTo(ClassLevel::class);
    }

    /**
     * Define the relationship with the school section.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function schoolSection()
    {
        return $this->belongsTo(\App\Models\SchoolSection::class);
    }

    /**
     * Define the relationship with the academic session.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admission')
            ->logFillable()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Admission has been {$eventName}");
    }
}
