<?php

namespace App\Models\Resource;

use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use FarhanShares\MediaMan\Traits\HasMedia;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class Syllabus
 *
 * Represents a syllabus entry in the school management system.
 *
 * @package App\Models\Resource
 * @property string $id
 * @property string $school_id
 * @property string $class_level_id
 * @property string $subject_id
 * @property string $term_id
 * @property string $topic
 * @property string|null $sub_topic
 * @property string|null $description
 * @property string $status
 * @property array|null $options
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Syllabus extends Model
{
    use BelongsToSchool, HasTableQuery, HasMedia, LogsActivity, SoftDeletes, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'syllabi';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'class_level_id',
        'subject_id',
        'term_id',
        'topic',
        'sub_topic',
        'description',
        'status',
        'options',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'options' => 'array',
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
        'options',
        'description',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected $globalFilterFields = [
        'topic',
        'sub_topic',
        'status',
    ];

    /**
     * Define the relationship with the class level.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classLevel()
    {
        return $this->belongsTo(\App\Models\Academic\ClassLevel::class, 'class_level_id');
    }

    /**
     * Define the relationship with the subject.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subject()
    {
        return $this->belongsTo(\App\Models\Academic\Subject::class, 'subject_id');
    }

    /**
     * Define the relationship with the term.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function term()
    {
        return $this->belongsTo(\App\Models\Academic\Term::class, 'term_id');
    }

    /**
     * Define the relationship with approval requests.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function approvals()
    {
        return $this->hasMany(SyllabusApproval::class);
    }

    /**
     * Get the latest approval request for the syllabus.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function latestApproval()
    {
        return $this->hasOne(SyllabusApproval::class)->latestOfMany();
    }

    /**
     * Get a specific option from the options JSON field.
     *
     * @param string $option
     * @return mixed
     */
    public function getOption(string $option)
    {
        return array_get($this->options, $option);
    }

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('syllabus')
            ->logFillable()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Syllabus has been {$eventName}");
    }

    /**
     * Register media collections for the syllabus.
     *
     * @return void
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('syllabus_files')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png'])
            ->useDisk('public');
    }
}
