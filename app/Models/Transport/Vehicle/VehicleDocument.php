<?php

namespace App\Models\Transport\Vehicle;

use App\Traits\BelongsToPrimaryModel;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use FarhanShares\MediaMan\Traits\HasMedia;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing a vehicle document in the school management system.
 *
 * Stores documents related to vehicles, such as insurance or registration.
 *
 * @property string $id Auto-incrementing primary key.
 * @property string $vehicle_id Associated vehicle ID.
 * @property string $title Document title (e.g., Insurance, Registration).
 * @property string|null $description Document description.
 * @property \Illuminate\Support\Carbon|null $date_of_expiry Expiry date of the document.
 * @property array|null $options Additional document options.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class VehicleDocument extends Model
{
    use HasFactory, LogsActivity, HasTableQuery, SoftDeletes, BelongsToPrimaryModel, HasMedia, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vehicle_documents';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'vehicle_id',
        'title',
        'description',
        'date_of_expiry',
        'options',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'options' => 'array',
        'date_of_expiry' => 'datetime',
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
        'title',
        'description',
    ];

    /**
     * Get the vehicle associated with the document.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the options for logging changes to the model.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('vehicle_document')
            ->setDescriptionForEvent(function ($event) {
                return "Vehicle document {$event}: {$this->title} for Vehicle ID {$this->vehicle_id}";
            })
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the school ID column name through the vehicle relationship.
     *
     * @return string
     */
    public static function getSchoolIdColumn(): string
    {
        return 'vehicle.school_id';
    }

    public function getRelationshipToPrimaryModel() {
        'vehicle';
    }
}
