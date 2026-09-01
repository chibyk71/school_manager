<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use App\Traits\HasConfig;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\State;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Address Model v4.0 – Production-Ready Polymorphic Address (Nigeria-First + Global)
 */
class Address extends Model
{
    use HasFactory;
    use BelongsToSchool;
    use HasConfig;
    use HasTableQuery;
    use LogsActivity;
    use SoftDeletes;
    use HasUuids;

    protected $fillable = [
        'school_id',
        'addressable_id',
        'addressable_type',
        'country_id',
        'state_id',
        'city_id',
        'address_line_1',
        'address_line_2',
        'landmark',
        'city_text',
        'postal_code',
        'type',
        'latitude',
        'longitude',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'latitude'   => 'decimal:7',
        'longitude'  => 'decimal:7',
    ];

    protected array $hiddenTableColumns = [
        'id',
        'school_id',
        'addressable_id',
        'addressable_type',
        'deleted_at',
    ];

    protected array $defaultHiddenColumns = [
        'latitude',
        'longitude',
        'created_at',
        'updated_at',
    ];

    protected array $globalFilterFields = [
        'address_line_1',
        'address_line_2',
        'landmark',
        'city_text',
        'postal_code',
    ];

    public function getConfigurableProperties(): array
    {
        return ['type'];
    }

    public function addressable()
    {
        return $this->morphTo();
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Address rows are not name-keyed config; partition by primary key so SchoolScope
     * does not reference a non-existent `name` column (required for HasAddress queries).
     */
    protected static function schoolScopePartitionColumns(): string|array
    {
        return 'id';
    }

    public function getFormattedAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->landmark ? "Near {$this->landmark}" : null,
            $this->city_text ?? $this->city?->name,
            $this->state?->name,
            $this->country?->name,
            $this->postal_code ? "({$this->postal_code})" : null,
        ]);

        return implode(', ', $parts) ?: 'No address details available';
    }

    public function scopePrimaryOnly($query)
    {
        return $query->where('is_primary', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
