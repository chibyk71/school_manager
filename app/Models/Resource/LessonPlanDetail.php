<?php

namespace App\Models\Resource;

use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use FarhanShares\MediaMan\Traits\HasMedia;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class LessonPlanDetail
 *
 * Represents a detailed entry for a lesson plan in the school management system.
 *
 * @package App\Models\Resource
 * @property int $id
 * @property string $school_id
 * @property int $lesson_plan_id
 * @property string $title
 * @property string|null $sub_title
 * @property string|null $objective
 * @property array $activity
 * @property array|null $teaching_method
 * @property array|null $evaluation
 * @property array|null $resources
 * @property int $duration
 * @property string|null $remarks
 * @property string $status
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class LessonPlanDetail extends Model
{
    use BelongsToSchool, HasTableQuery, HasMedia, LogsActivity, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lesson_plan_details';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'lesson_plan_id',
        'title',
        'sub_title',
        'objective',
        'activity',
        'teaching_method',
        'evaluation',
        'resources',
        'duration',
        'remarks',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'activity' => 'array',
        'teaching_method' => 'array',
        'evaluation' => 'array',
        'resources' => 'array',
        'duration' => 'integer',
        'status' => 'string',
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
        'activity',
        'teaching_method',
        'evaluation',
        'resources',
        'remarks',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected $globalFilterFields = [
        'title',
        'sub_title',
        'objective',
        'status',
    ];

    /**
     * Define the relationship with the lesson plan.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function lessonPlan()
    {
        return $this->belongsTo(LessonPlan::class);
    }

    /**
     * Define the relationship with approval requests.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function approvals()
    {
        return $this->hasMany(LessonPlanDetailApproval::class);
    }

    /**
     * Get the latest approval request for the lesson plan detail.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function latestApproval()
    {
        return $this->hasOne(LessonPlanDetailApproval::class)->latestOfMany();
    }

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('lesson_plan_detail')
            ->logFillable()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Register media collections for the lesson plan detail.
     *
     * @return void
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('lesson_plan_detail_files')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png'])
            ->useDisk('public');
    }
}
