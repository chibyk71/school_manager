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
 *
 * Core entity for the Address Management Module. Represents a single address that can be attached polymorphically
 * to any model (Student, Staff, Parent, School, Vendor, Vehicle, etc.).
 *
 * Features / Problems Solved:
 * - Fully leverages the addresses migration (UUID primary key, polymorphic addressable, nnjeim/world cascading,
 *   Nigeria-specific fields: landmark + city_text fallback, postal_code, type classification, primary flag,
 *   optional geolocation with high precision).
 * - Multi-tenancy via BelongsToSchool trait (school_id scoped automatically).
 * - Soft deletes for safe recovery.
 * - Activity logging (Spatie) on all fillable fields – only dirty changes, no empty logs.
 * - DataTable integration (HasTableQuery): hidden/default-hidden columns defined, global search on human-readable fields.
 * - Configurable 'type' field via HasConfig trait – allows admin to customize dropdown options per school.
 * - Human-readable formatted accessor with smart fallback (city_text preferred over city name when provided).
 * - Proper casts (boolean for is_primary, decimal:7 for lat/long ≈ 10–11 meter accuracy).
 * - UUID primary key for security and distributed safety.
 * - Clean relationships (morphTo addressable + belongsTo country/state/city).
 *
 * Fits into the Address Module:
 * - Central data structure used by HasAddress trait, AddressService, AddressController, and all frontend components.
 * - Primary flag is managed exclusively via HasAddress trait (ensures only one primary per owner).
 * - Formatted attribute used heavily in AddressList.vue / AddressItem.vue for display.
 * - Configurable type options consumed by AddressForm.vue dropdown.
 *
 * Usage Examples:
 *   $address->formatted;                    // Human-readable string
 *   $address->addressable;                  // Polymorphic owner (e.g., Student instance)
 *   $address->country->name;                // Via nnjeim/world relations
 *   Address::primaryOnly()->get();          // Scope for primary addresses
 *
 * Dependencies:
 * - nnjeim/world package (Country, State, City models)
 * - Traits: BelongsToSchool, HasConfig, HasTableQuery, LogsActivity, SoftDeletes, HasUuids
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

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
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

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'latitude'   => 'decimal:7',
        'longitude'  => 'decimal:7',
    ];

    // ── DataTable Integration (HasTableQuery) ─────────────────────────────────────

    /**
     * Columns that must never be exposed to the frontend (sensitive or internal).
     */
    protected array $hiddenTableColumns = [
        'id',
        'school_id',
        'addressable_id',
        'addressable_type',
        'deleted_at',
    ];

    /**
     * Columns sent to frontend but hidden by default (user can toggle visibility).
     */
    protected array $defaultHiddenColumns = [
        'latitude',
        'longitude',
        'created_at',
        'updated_at',
    ];

    /**
     * Fields included in global free-text search within DataTables.
     */
    protected array $globalFilterFields = [
        'address_line_1',
        'address_line_2',
        'landmark',
        'city_text',
        'postal_code',
    ];

    // ── HasConfig Integration ─────────────────────────────────────────────────────

    /**
     * Properties that can be configured per school (e.g., allowed address types).
     *
     * @return array<string>
     */
    public function getConfigurableProperties(): array
    {
        return ['type'];
    }

    // ── Relationships ─────────────────────────────────────────────────────────────

    /**
     * The model that owns this address (Student, Staff, School, etc.).
     */
    public function addressable()
    {
        return $this->morphTo();
    }

    /**
     * Country relationship (nnjeim/world).
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * State/province relationship (nnjeim/world).
     */
    public function state()
    {
        return $this->belongsTo(State::class);
    }

    /**
     * City/town relationship (nnjeim/world).
     */
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────────

    /**
     * Human-readable formatted address.
     *
     * Order prioritises Nigerian-style navigation: street → landmark → city_text (fallback) → state → country → postal.
     * Returns a clean string or fallback message.
     */
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

    // ── Scopes ────────────────────────────────────────────────────────────────────

    /**
     * Scope to retrieve only primary addresses.
     */
    public function scopePrimaryOnly($query)
    {
        return $query->where('is_primary', true);
    }

    // ── Activity Logging (Spatie) ─────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}