<?php

namespace App\Models\Transport\Vehicle;

use App\Models\Configuration\Config;
use App\Models\School;
use App\Models\Transport\Route;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasConfig;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing a vehicle in the school management system.
 *
 * Vehicles are school-scoped and associated with routes, drivers, and documents.
 *
 * @property string $id Auto-incrementing primary key.
 * @property string $school_id Associated school ID.
 * @property string $name Vehicle name.
 * @property string $registration_number Vehicle registration number.
 * @property string $make Vehicle make (e.g., Toyota).
 * @property string $model Vehicle model (e.g., HiAce).
 * @property int $max_seating_capacity Maximum seating capacity.
 * @property bool $is_owned Whether the vehicle is owned by the school.
 * @property string|null $owner_name Owner name (if not owned).
 * @property string|null $owner_company_name Owner company name (if not owned).
 * @property string|null $owner_phone Owner phone number (if not owned).
 * @property string|null $owner_email Owner email (if not owned).
 * @property string|null $vehicle_fuel_type Associated fuel type ID.
 * @property int $max_fuel_capacity Fuel capacity in liters.
 * @property bool $is_active Whether the vehicle is active.
 * @property array|null $options Additional vehicle options.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Vehicle extends Model
{
    use HasFactory, LogsActivity, HasTableQuery, SoftDeletes, BelongsToSchool, HasUuids, HasConfig;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vehicles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'school_id',
        'registration_number',
        'make',
        'model',
        'max_seating_capacity',
        'is_owned',
        'owner_name',
        'owner_company_name',
        'owner_phone',
        'owner_email',
        'fuel_type',
        'max_fuel_capacity',
        'is_active',
        'options',
    ];

    protected $attributes = [
        'is_owned' => true,
        'is_active' => true,
        'fuel_type' => null,
        'type' => null,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_owned' => 'boolean',
        'is_active' => 'boolean',
        'options' => 'array',
    ];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'id',
        'school_id',
        'vehicle_fuel_type_id',
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
        'registration_number',
        'make',
        'model',
        'owner_name',
        'owner_company_name',
    ];

    /**
     * Get the school that owns the vehicle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }
/** Set a vehicle-type config (system-wide) */
    public function setVehicleType(string $type): Config
    {
        return $this->addConfig('vehicle_type', $type);
    }

    /** Set a fuel-type config (school-specific when a school is active) */
    public function setFuelType(string $fuel): Config
    {
        return $this->addConfig('vehicle_fuel_type', $fuel);
    }

    public function getFuelTypeAttribute()
    {
        return $this->fuelType();
    }

    public function getTypeAttribute()
    {
        return $this->vehicleType();
    }

    /** Get the effective vehicle type (school → system) */
    public function vehicleType(): ?string
    {
        return $this->getConfig('vehicle_type');
    }

    /** Get the effective fuel type */
    public function fuelType(): ?string
    {
        return $this->getConfig('vehicle_fuel_type');
    }

    /** Get both at once – returns ['vehicle_type'=>…, 'vehicle_fuel_type'=>…] */
    public function vehicleConfig(): array
    {
        return $this->getConfigs(['vehicle_type', 'vehicle_fuel_type'])
                    ->pluck('value', 'name')
                    ->toArray();
    }

    /**
     * Get the documents associated with the vehicle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function vehicleDocuments()
    {
        return $this->hasMany(VehicleDocument::class);
    }

    /**
     * Get the incharges associated with the vehicle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function vehicleIncharges()
    {
        return $this->hasMany(VehicleIncharge::class);
    }

    /**
     * Get the driver assignments for the vehicle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function driverAssignments()
    {
        return $this->hasMany(DriverAssignment::class);
    }

    /**
     * Get the current driver assignment for the vehicle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function currentDriver()
    {
        return $this->hasOne(DriverAssignment::class)->whereNull('unassigned_at');
    }

    /**
     * Get the routes assigned to the vehicle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function routes()
    {
        return $this->belongsToMany(Route::class, 'route_vehicle', 'vehicle_id', 'route_id')
                    ->withPivot('user_id')
                    ->withTimestamps();
    }

    /**
     * Get the users assigned to the vehicle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'route_vehicle', 'vehicle_id', 'user_id')
                    ->withPivot('route_id')
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
            ->useLogName('vehicle')
            ->setDescriptionForEvent(function ($event) {
                return "Vehicle {$event}: {$this->name} ({$this->registration_number})";
            })
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
