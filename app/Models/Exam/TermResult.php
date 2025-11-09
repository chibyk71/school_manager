<?php

namespace App\Models\Exam;

use App\Models\Academic\ClassLevel;
use App\Models\Academic\Student;
use App\Models\Academic\Term;
use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing a student's term result in the school management system.
 *
 * @property string $id Primary key
 * @property string $school_id UUID of the associated school
 * @property string $student_id UUID of the associated student
 * @property string $term_id ID of the associated term
 * @property string $class_id ID of the associated class level
 * @property float $total_score Total score for the term
 * @property float $average_score Average score for the term
 * @property int $position Student's position in the class
 * @property string|null $class_teacher_remark Class teacher's remark
 * @property string|null $head_teacher_remark Head teacher's remark
 * @property string $grade Student's grade for the term
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TermResult extends Model
{
    use BelongsToSchool, HasTableQuery, LogsActivity, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'term_id',
        'class_id',
        'total_score',
        'average_score',
        'position',
        'class_teacher_remark',
        'head_teacher_remark',
        'grade',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_score' => 'decimal:2',
        'average_score' => 'decimal:2',
        'position' => 'integer',
    ];

    /**
     * The attributes used for global filtering.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = ['class_teacher_remark', 'head_teacher_remark', 'grade'];

    /**
     * The attributes hidden from table queries.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = ['school_id', 'created_at', 'updated_at'];

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
     * Define the relationship with the Term model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * Define the relationship with the ClassLevel model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classLevel()
    {
        return $this->belongsTo(ClassLevel::class, 'class_id');
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
            ->useLogName('term_result');
    }
}
