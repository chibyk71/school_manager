<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\State;

/**
 * Address — foundational polymorphic address entity (Phase 1).
 *
 * Ownership: addressable (morphTo) only. No school_id / tenant_id.
 * Lifecycle: permanent deletion only (no SoftDeletes).
 * Type: Dynamic Enum–backed string (InDynamicEnum / DynamicEnumSeeder).
 * Primary: is_primary defaults false; 0..1 primary per owner (DB partial unique where supported).
 *
 * Authorization, tenant context, and CRUD capability live on the owning resource
 * (and later phases of the Address module), not on Address itself.
 */
class Address extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
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

    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Human-readable single-line representation.
     * Prefer structured city name when city_id is set; otherwise city_text.
     */
    public function getFormattedAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->landmark ? "Near {$this->landmark}" : null,
            $this->city_id ? $this->city?->name : $this->city_text,
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
}
