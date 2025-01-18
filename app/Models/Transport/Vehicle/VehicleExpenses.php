<?php

namespace App\Models\Transport\Vehicle;

use App\Traits\HasConfig;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VehicleExpenses extends Model
{
    /** @use HasFactory<\Database\Factories\Transport\Vehicle\VehicleExpensesFactory> */
    use HasFactory, LogsActivity, HasConfig;

    protected $fillable = [
        'amount',
        'liter',
        'date_of_expense',
        'vehicle_id',
        'next_due_date',
        'description',
        'options'
    ];

    protected $appends = [
        'type'
    ];

    protected $casts = [
        'options' => 'json',
        'date_of_expense',
        'amount' => 'decimal:2'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('vehicle')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }

    /**
     * Get the vehicle that owns the VehicleExpenses
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function getTypeAttribute() {
        return $this->configs();
    }
}
