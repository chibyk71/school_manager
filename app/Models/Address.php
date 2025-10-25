<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing an address for an entity (e.g., school, staff).
 *
 * @property string $id UUID primary key.
 * @property string|null $school_id Associated school ID.
 * @property string $addressable_type Morph type for addressable entity.
 * @property string $addressable_id Morph ID for addressable entity.
 * @property string $address Street address.
 * @property string $city City name.
 * @property string $state State or province.
 * @property string|null $postal_code Postal or ZIP code.
 * @property int $country_id Foreign key to countries table.
 * @property bool $is_primary Indicates if this is the primary address.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Address extends Model
{
    use HasFactory, BelongsToSchool, LogsActivity, HasTableQuery, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'school_id',
        'address',
        'city',
        'state',
        'postal_code',
        'country_id',
        'addressable_id',
        'addressable_type',
        'is_primary',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'id',
        'school_id',
        'addressable_id',
        'addressable_type',
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
        'address',
        'city',
        'state',
        'postal_code',
    ];

    /**
     * The polymorphic entity associated with the address.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function addressable()
    {
        return $this->morphTo();
    }

    /**
     * Get the country associated with the address.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * Get the options for logging changes to the model.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
