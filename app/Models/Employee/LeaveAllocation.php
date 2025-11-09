<?php

namespace App\Models\Employee;

use App\Models\Academic\AcademicSession;
use App\Models\Configuration\LeaveType;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * LeaveAllocation model representing leave allocations for employees in a school.
 *
 * @property string $id
 * @property string $school_id
 * @property string $user_id
 * @property string $leave_type_id
 * @property int $no_of_days
 * @property string $academic_session_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class LeaveAllocation extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, BelongsToSchool, HasTableQuery, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'leave_allocations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'user_id',
        'leave_type_id',
        'no_of_days',
        'academic_session_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'no_of_days' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'school_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'no_of_days',
    ];

    /**
     * Configure activity logging options.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('leave_allocation')
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Leave allocation for user ID {$this->user_id} and leave type ID {$this->leave_type_id} was {$eventName}");
    }

    /**
     * Define the relationship to the User model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Define the relationship to the LeaveType model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    /**
     * Define the relationship to the AcademicSession model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    /**
     * Scope a query to only include leave allocations for a specific academic session.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $academicSessionId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForAcademicSession($query, int $academicSessionId)
    {
        return $query->where('academic_session_id', $academicSessionId);
    }
}