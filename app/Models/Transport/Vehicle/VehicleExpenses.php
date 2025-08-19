<?php

namespace App\Models\Transport\Vehicle;

use App\Models\Model;
use App\Traits\HasConfig;
use App\Traits\HasTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VehicleExpenses extends Model
{
    /** @use HasFactory<\Database\Factories\Transport\Vehicle\VehicleExpensesFactory> */
    use HasFactory, LogsActivity, HasConfig, HasTransaction;

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

    public function getTransactionType(): string
    {
        return 'expense';
    }

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
