<?php

namespace App\Models\Configuration;

use App\Models\Employee\LeaveAllocation;
use App\Models\Employee\LeaveRequest;
use App\Models\School;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * LeaveType model representing types of leave available in a school.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string|null $description
 * @property int|null $max_days
 * @property array|null $options
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class LeaveType extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, BelongsToSchool, HasTableQuery, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'leave_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'description',
        'max_days',
        'options',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'max_days' => 'integer',
        'options' => 'array',
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
        'options',
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
        'name',
        'description',
    ];

    /**
     * Configure activity logging options.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('leave_type')
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Leave type {$this->name} was {$eventName}");
    }

    /**
     * Define the relationship to the School model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Define the relationship to the LeaveRequest model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Define the relationship to the LeaveAllocation model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function leaveAllocations()
    {
        return $this->hasMany(LeaveAllocation::class);
    }
}
