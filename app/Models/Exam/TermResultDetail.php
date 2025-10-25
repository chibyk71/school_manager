<?php

namespace App\Models\Exam;

use App\Models\Academic\Grade;
use App\Models\Academic\Subject;
use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing subject-specific details of a student's term result in the school management system.
 *
 * @property int $id Primary key
 * @property string $school_id UUID of the associated school
 * @property int $term_result_id ID of the associated term result
 * @property string $subject_id UUID of the associated subject
 * @property int $grade_id ID of the associated grade
 * @property float $score Subject-specific score
 * @property string|null $class_teacher_remark Class teacher's remark for the subject
 * @property string|null $head_teacher_remark Head teacher's remark for the subject
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TermResultDetail extends Model
{
    use BelongsToSchool, HasTableQuery, LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'term_result_details';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'term_result_id',
        'subject_id',
        'grade_id',
        'score',
        'class_teacher_remark',
        'head_teacher_remark',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'score' => 'decimal:2',
    ];

    /**
     * The attributes used for global filtering.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = ['class_teacher_remark', 'head_teacher_remark'];

    /**
     * The attributes hidden from table queries.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = ['school_id', 'created_at', 'updated_at'];

    /**
     * Define the relationship with the TermResult model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function termResult()
    {
        return $this->belongsTo(TermResult::class);
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
     * Define the relationship with the Grade model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function grade()
    {
        return $this->belongsTo(Grade::class);
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
            ->useLogName('term_result_detail');
    }
}
