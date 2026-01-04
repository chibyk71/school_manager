<?php

namespace App\Models\Resource;

use App\Models\Academic\ClassLevel;
use App\Models\Academic\Subject;
use App\Models\Employee\Staff;
use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class LessonPlan
 *
 * Represents a lesson plan in the school management system.
 *
 * @package App\Models\Resource
 * @property string $id
 * @property string $school_id
 * @property string $class_level_id
 * @property string $subject_id
 * @property string $sylabus_detail_id
 * @property string $topic
 * @property \Illuminate\Support\Carbon $date
 * @property string $objective
 * @property array $material
 * @property array $assessment
 * @property string $staff_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class LessonPlan extends Model
{
    use BelongsToSchool, HasTableQuery, LogsActivity, SoftDeletes, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lesson_plans';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'class_level_id',
        'subject_id',
        'syllabus_detail_id',
        'topic',
        'date',
        'objective',
        'material',
        'assessment',
        'staff_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'material' => 'array',
        'assessment' => 'array',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'media',
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
        'material',
        'assessment',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected $globalFilterFields = [
        'topic',
        'objective',
    ];

    /**
     * Define the relationship with the syllabus detail.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sylabusDetail()
    {
        return $this->belongsTo(SyllabusDetail::class, 'syllabus_detail_id');
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
     * Define the relationship with the subject.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Define the relationship with the staff.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Define the relationship with lesson plan details.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function lessonPlanDetails()
    {
        return $this->hasMany(LessonPlanDetail::class);
    }

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('lesson_plan')
            ->logFillable()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }

    /**
     * Register media collections for the lesson plan.
     *
     * @return void
     */
    // public function registerMediaCollections(): void
    // {
    //     $this->addMediaCollection('lesson_plan_files')
    //         ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png'])
    //         ->useDisk('public');
    // }
}
