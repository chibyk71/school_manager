<?php

namespace App\Models\Resource;

use App\Models\Employee\Staff;
use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\BelongsToSections;
use App\Traits\HasConfig;
use App\Traits\HasTableQuery;
use FarhanShares\MediaMan\Traits\HasMedia;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class Assignment
 *
 * Represents an assignment in the school management system.
 *
 * @package App\Models\Resource
 * @property int $id
 * @property string $school_id
 * @property int $class_level_id
 * @property string $subject_id
 * @property string $title
 * @property string|null $description
 * @property int $term_id
 * @property int $total_mark
 * @property \Illuminate\Support\Carbon $due_date
 * @property string $teacher_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Assignment extends Model
{
    use BelongsToSchool, BelongsToSections, HasMedia, HasConfig, HasTableQuery, LogsActivity, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'assignments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'class_level_id',
        'subject_id',
        'title',
        'description',
        'term_id',
        'total_mark',
        'due_date',
        'teacher_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'due_date' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'media',
        'type',
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
        'title',
        'description',
    ];

    /**
     * Get the configuration type attribute.
     *
     * @return array The configuration names.
     */
    public function getTypeAttribute(): array
    {
        return $this->configs()->pluck('name')->toArray();
    }

    /**
     * Define the relationship with the teacher (Staff model).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function teacher()
    {
        return $this->belongsTo(Staff::class, 'teacher_id');
    }

    /**
     * Define the relationship with the class level.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classLevel()
    {
        return $this->belongsTo('App\Models\Academic\ClassLevel', 'class_level_id');
    }

    /**
     * Define the relationship with the subject.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subject()
    {
        return $this->belongsTo('App\Models\Academic\Subject', 'subject_id');
    }

    /**
     * Define the relationship with the term.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function term()
    {
        return $this->belongsTo('App\Models\Academic\Term', 'term_id');
    }

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('assignment')
            ->logFillable()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }

    /**
     * Register media collections for the assignment.
     *
     * @return void
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('assignments')
            ->acceptsMimeTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'])
            ->useDisk('public');
    }
}