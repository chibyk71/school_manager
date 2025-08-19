<?php

namespace App\Models\Transport\Vehicle;

use App\Models\Model;
use App\Models\School;
use App\Models\Transport\Route;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Vehicle extends Model
{
    /** @use HasFactory<\Database\Factories\Transport\Vehicle\VehicleFactory> */
    use HasFactory, LogsActivity;

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
        'vehicle_fuel_type_id',
        'max_fuel_capacity',
        'is_active',
        'options'
    ];
    protected $casts = ['options' => 'array'];
    protected $primaryKey = 'id';
    protected $table = 'vehicles';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('vehicle')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }

    /**
     * Get the school that owns the Vehicle
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function vehicleDocuments()
    {
        return $this->hasMany('App\Models\Transport\Vehicle\VehicleDocument');
    }

    public function vehicleIncharges()
    {
        return $this->hasMany('App\Models\Transport\Vehicle\VehicleIncharge');
    }

    public function driverAssignments()
    {
        return $this->hasMany(DriverAssignment::class);
    }

    public function currentDriver()
    {
        return $this->hasOne(DriverAssignment::class)->whereNull('unassigned_at');
    }

    /**
     * The routes that belong to the Vehicle
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function routes()
    {
        return $this->belongsToMany(Route::class, 'route_vehicle', 'vehicle_id', 'route_id')
        ->withPivot('user_id');
    }

    /**
     * The users that belong to the Vehicle
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'route_vehicle', 'vehicle_id', 'user_id')
        ->withPivot('route_id');
    }
}
