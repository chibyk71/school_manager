<?php

namespace App\Models\Resource;

use App\Models\Employee\Staff;
use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use FarhanShares\MediaMan\Traits\HasMedia;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class AssignmentSubmission
 *
 * Represents an assignment submission in the school management system.
 *
 * @package App\Models\Resource
 * @property int $id
 * @property string $student_id
 * @property int $assignment_id
 * @property string|null $answer_text
 * @property float|null $mark_obtained
 * @property string $status
 * @property \Illuminate\Support\Carbon $submitted_at
 * @property string|null $graded_by
 * @property string|null $remark
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class AssignmentSubmission extends Model
{
    use BelongsToSchool, HasMedia, HasTableQuery, LogsActivity, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'assignment_submissions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'assignment_id',
        'answer_text',
        'mark_obtained',
        'status',
        'submitted_at',
        'graded_by',
        'remark',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'submitted_at' => 'datetime',
        'mark_obtained' => 'float',
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
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected $globalFilterFields = [
        'answer_text',
        'remark',
    ];

    /**
     * Define the relationship with the student.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
        return $this->belongsTo('App\Models\People\Student', 'student_id');
    }

    /**
     * Define the relationship with the assignment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function assignment()
    {
        return $this->belongsTo('App\Models\Resource\Assignment', 'assignment_id');
    }

    /**
     * Define the relationship with the staff who graded the submission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function gradedBy()
    {
        return $this->belongsTo(Staff::class, 'graded_by');
    }

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('assignment_submission')
            ->logFillable()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }

    /**
     * Register media collections for the assignment submission.
     *
     * @return void
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('submissions')
            ->acceptsMimeTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'])
            ->useDisk('public');
    }
}