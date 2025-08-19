<?php

namespace App\Models\Transport\Vehicle;

use App\Models\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DriverAssignment extends Model
{
    use LogsActivity;

    protected $table = 'driver_assignments';

    protected $fillable = [
        'staff_id',
        'vehicle_id',
        'effective_date',
        'unassigned_at',
        'options',
    ];

    protected $casts = [
        'options' => 'array',
        'effective_date' => 'date',
        'unassigned_at' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
        ->logOnly(['*']);
    }

    public function staff()
    {
        return $this->belongsTo('App\Models\Staff');
    }

    public function vehicle()
    {
        return $this->belongsTo('App\Models\Transport\Vehicle\Vehicle');
    }

}
