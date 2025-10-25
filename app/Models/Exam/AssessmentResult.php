<?php

namespace App\Models\Exam;

use App\Models\Academic\ClassSection;
use App\Models\Academic\Grade;
use App\Models\Academic\Student;
use App\Models\Academic\Subject;
use App\Models\Employee\Staff;
use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing an assessment result for a student in the school management system.
 *
 * @property int $id Primary key
 * @property string $assessment_id UUID of the associated assessment
 * @property string $student_id UUID of the associated student
 * @property string $subject_id UUID of the associated subject
 * @property string $school_id UUID of the associated school
 * @property string $result Assessment result (e.g., score)
 * @property string|null $remark Optional remark
 * @property string $class_section_id ID of the associated class section
 * @property string $graded_by UUID of the staff who graded the assessment
 * @property string|null $grade_id ID of the associated grade
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class AssessmentResult extends Model
{
    use LogsActivity, BelongsToSchool, HasTableQuery;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'assessment_results';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'assessment_id',
        'student_id',
        'subject_id',
        'school_id',
        'result',
        'grade_id',
        'remark',
        'class_section_id',
        'graded_by',
    ];

    /**
     * The attributes used for global filtering.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = ['result', 'remark'];

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
     * Define the relationship with the Student model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Define the relationship with the Grade model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function grade()
    {
        return $this->belongsTo(Grade::class);
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
     * Define the relationship with the Staff model (grader).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function gradedBy()
    {
        return $this->belongsTo(Staff::class, 'graded_by');
    }

    /**
     * Define the relationship with the Subject model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
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
            ->useLogName('assessment_result');
    }
}
