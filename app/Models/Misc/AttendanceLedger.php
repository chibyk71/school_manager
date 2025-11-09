<?php

namespace App\Models\Misc;

use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class AttendanceLedger
 *
 * Represents an attendance record for a student or staff in the school management system.
 *
 * @package App\Models\Misc
 * @property string $id
 * @property string $school_id
 * @property string $attendance_session_id
 * @property string $attendable_id
 * @property string $attendable_type
 * @property string $status
 * @property string|null $remarks
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class AttendanceLedger extends Model
{
    use BelongsToSchool, HasTableQuery, LogsActivity, SoftDeletes, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'attendance_ledgers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'attendance_session_id',
        'attendable_id',
        'attendable_type',
        'status',
        'remarks',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'string',
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
        'remarks',
        'attendable_id',
        'attendable_type',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected $globalFilterFields = [
        'status',
    ];

    /**
     * Define the polymorphic relationship with the attendable entity (e.g., Student, Staff).
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function attendable()
    {
        return $this->morphTo();
    }

    /**
     * Define the relationship with the attendance session.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function attendanceSession()
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('attendance_ledger')
            ->logFillable()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "AttendanceLedger has been {$eventName}");
    }
}
