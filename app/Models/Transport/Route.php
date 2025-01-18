<?php

namespace App\Models\Transport;

use App\Models\Finance\Fee;
use App\Models\Transport\Vehicle\Vehicle;
use App\Models\User;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Route extends Model
{
    /** @use HasFactory<\Database\Factories\Transport\RouteFactory> */
    use HasFactory, LogsActivity, SoftDeletes, BelongsToSchool;

    protected $fillable = [
        'name',
        'description',
        'status',
        'starting_piont',
        'ending_point',
        'distance',
        'duration',
        'fee_id',
        'school_id'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'status', 'starting_piont', 'ending_point', 'distance', 'duration', 'fee_id'])
            ->logOnlyDirty();
    }

    public function fee() {
        return $this->belongsTo(Fee::class);
    }

    /**
     * The roles that belong to the Route
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function vehicle()
    {
        return $this->belongsToMany(Vehicle::class, 'route_vehicle', 'route_id', 'vehicle_id')->withPivot('user_id');
    }

    /**
     * The users that belong to the Route
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'route_vehicle', 'route_id', 'user_id')
        ->withPivot('vehicle_id');
    }
}
