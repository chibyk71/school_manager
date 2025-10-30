<?php

namespace App\Models\Transport\Vehicle;

use App\Models\Employee\Staff;
use App\Models\Model;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing a driver assignment in the school management system.
 *
 * Tracks driver assignments to vehicles with effective and unassignment dates.
 *
 * @property int $id Auto-incrementing primary key.
 * @property string $vehicle_id Associated vehicle ID.
 * @property string $staff_id Associated staff ID (driver).
 * @property \Illuminate\Support\Carbon $effective_date Effective date of assignment.
 * @property \Illuminate\Support\Carbon|null $unassigned_at Date when driver was unassigned.
 * @property array|null $options Additional assignment options.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class DriverAssignment extends Model
{
    use HasFactory, LogsActivity, HasTableQuery, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'driver_assignments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'vehicle_id',
        'staff_id',
        'effective_date',
        'unassigned_at',
        'options',
        'role'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'options' => 'array',
        'effective_date' => 'datetime',
        'unassigned_at' => 'datetime',
    ];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'id',
        'vehicle_id',
        'staff_id',
        'options',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Get the vehicle associated with the assignment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the staff (driver) associated with the assignment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * Get the user associated with the staff.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough
     */
    public function user()
    {
        return $this->hasOneThrough(
            \App\Models\User::class,
            Staff::class,
            'id',
            'id',
            'staff_id',
            'user_id'
        );
    }

    /**
     * Get the options for logging changes to the model.
     *
     * @ return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('driver_assignment')
            ->setDescriptionForEvent(function ($event) {
                return "Driver assignment {$event}: Vehicle ID {$this->vehicle_id}, Staff ID {$this->staff_id}";
            })
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
