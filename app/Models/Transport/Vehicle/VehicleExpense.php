<?php

namespace App\Models\Transport\Vehicle;

use App\Models\Model;
use App\Traits\BelongsToPrimaryModel;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Model representing a vehicle expense in the school management system.
 *
 * Tracks expenses related to vehicles, such as fuel or maintenance, with optional media (e.g., receipts).
 *
 * @property string $id Auto-incrementing primary key.
 * @property string $vehicle_id Associated vehicle ID.
 * @property float $amount Expense amount.
 * @property float|null $liters Fuel volume in liters (if applicable).
 * @property \Illuminate\Support\Carbon $date_of_expense Date of the expense.
 * @property \Illuminate\Support\Carbon|null $next_due_date Next due date for recurring expenses.
 * @property string|null $description Expense description.
 * @property array|null $options Additional expense options.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class VehicleExpense extends Model implements HasMedia
{
    use HasFactory, LogsActivity, HasTableQuery, SoftDeletes, BelongsToPrimaryModel, InteractsWithMedia, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vehicle_expenses';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'vehicle_id',
        'amount',
        'liters',
        'date_of_expense',
        'next_due_date',
        'description',
        'options',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'float',
        'liters' => 'float',
        'date_of_expense' => 'datetime',
        'next_due_date' => 'datetime',
        'options' => 'array',
    ];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'id',
        'vehicle_id',
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
        'description',
    ];

    /**
     * Get the vehicle associated with the expense.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function getRelationshipToPrimaryModel(): string
    {
        return 'vehicle';
    }

    /**
     * Get the options for logging changes to the model.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('vehicle_expense')
            ->setDescriptionForEvent(function ($event) {
                $description = "Vehicle expense {$event}: {$this->amount} for Vehicle ID {$this->vehicle_id}";
                if ($event === 'created' && $this->hasMedia('VehicleExpenses')) {
                    $description .= ' with attached media';
                }
                return $description;
            })
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
