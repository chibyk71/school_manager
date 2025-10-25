<?php

namespace App\Models\Transport;

use App\Models\Finance\Fee;
use App\Models\Model;
use App\Models\Transport\Vehicle\Vehicle;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing a transport route in the school management system.
 *
 * Routes are school-scoped and associated with vehicles, users (drivers/students), and fees.
 *
 * @property int $id Auto-incrementing primary key.
 * @property string $name Route name.
 * @property string|null $description Route description.
 * @property string $status Route status (e.g., active, inactive).
 * @property string $starting_point Starting point of the route.
 * @property string $ending_point Ending point of the route.
 * @property string $distance Route distance (e.g., '10 km').
 * @property string $duration Route duration (e.g., '30 minutes').
 * @property int|null $fee_id Associated fee ID.
 * @property string $school_id Associated school ID.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Route extends Model
{
    use HasFactory, LogsActivity, SoftDeletes, BelongsToSchool, HasTableQuery;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'status',
        'starting_point',
        'ending_point',
        'distance',
        'duration',
        'fee_id',
        'school_id',
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
    protected array $hiddenTableColumns = [
        'id',
        'school_id',
        'fee_id',
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
        'starting_point',
        'ending_point',
    ];

    /**
     * Get the fee associated with the route.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function fee()
    {
        return $this->belongsTo(Fee::class);
    }

    /**
     * Get the vehicles assigned to the route.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function vehicles()
    {
        return $this->belongsToMany(Vehicle::class, 'route_vehicle', 'route_id', 'vehicle_id')
                    ->withPivot('user_id')
                    ->withTimestamps();
    }

    /**
     * Get the users (drivers/students) assigned to the route.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'route_vehicle', 'route_id', 'user_id')
                    ->withPivot('vehicle_id')
                    ->withTimestamps();
    }

    /**
     * Get the options for logging changes to the model.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('route')
            ->setDescriptionForEvent(function ($event) {
                return "Route {$event}: {$this->name}";
            })
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
