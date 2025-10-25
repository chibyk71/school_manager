<?php

namespace App\Models\Misc;

use App\Models\Model;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasConfig;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class AttendanceSession
 *
 * Represents an attendance session for a class in the school management system.
 *
 * @package App\Models\Misc
 * @property int $id
 * @property string $school_id
 * @property int $class_section_id
 * @property int $class_period_id
 * @property string $manager_id
 * @property string $name
 * @property string|null $description
 * @property string $date_effective
 * @property array $configs
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class AttendanceSession extends Model
{
    use BelongsToSchool, HasConfig, HasTableQuery, LogsActivity, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'attendance_sessions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'class_section_id',
        'class_period_id',
        'manager_id',
        'name',
        'description',
        'date_effective',
        'configs',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_effective' => 'date',
        'configs' => 'array',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = ['type'];

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
        'configs',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected $globalFilterFields = [
        'name',
        'date_effective',
    ];

    /**
     * Get the type attribute (alias for configs).
     *
     * @return array|null
     */
    public function getTypeAttribute()
    {
        return $this->configs;
    }

    /**
     * Define the relationship with the class section.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classSection()
    {
        return $this->belongsTo(\App\Models\ClassSection::class);
    }

    /**
     * Define the relationship with the class period.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classPeriod()
    {
        return $this->belongsTo(\App\Models\ClassPeriod::class);
    }

    /**
     * Define the relationship with the manager (user).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Define the relationship with attendance ledgers.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attendanceLedgers()
    {
        return $this->hasMany(AttendanceLedger::class);
    }

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('attendance_session')
            ->logFillable()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "AttendanceSession has been {$eventName}");
    }
}
