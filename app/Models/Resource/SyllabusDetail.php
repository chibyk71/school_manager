<?php

namespace App\Models\Resource;

use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class SyllabusDetail
 *
 * Represents a detailed entry for a syllabus in the school management system.
 *
 * @package App\Models\Resource
 * @property string $id
 * @property string $school_id
 * @property string $syllabus_id
 * @property int $week
 * @property string|null $objectives
 * @property string $topic
 * @property array|null $sub_topics
 * @property string|null $description
 * @property array|null $resources
 * @property string $status
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class SyllabusDetail extends Model
{
    use BelongsToSchool, HasTableQuery, LogsActivity, SoftDeletes, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'syllabus_details';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'syllabus_id',
        'week',
        'objectives',
        'topic',
        'sub_topics',
        'description',
        'resources',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sub_topics' => 'array',
        'resources' => 'array',
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
        'description',
        'objectives',
        'sub_topics',
        'resources',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected $globalFilterFields = [
        'topic',
        'status',
        'week',
    ];

    /**
     * Define the relationship with the syllabus.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function syllabus()
    {
        return $this->belongsTo(Syllabus::class);
    }

    /**
     * Define the relationship with approval requests.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function approvals()
    {
        return $this->hasMany(SyllabusDetailApproval::class);
    }

    /**
     * Get the latest approval request for the syllabus detail.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function latestApproval()
    {
        return $this->hasOne(SyllabusDetailApproval::class)->latestOfMany();
    }

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('syllabus_detail')
            ->logFillable()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "SyllabusDetail has been {$eventName}");
    }
}
