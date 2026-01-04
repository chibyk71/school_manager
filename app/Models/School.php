<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\Transport\Vehicle\Vehicle;
use App\Traits\BelongsToSections;
use App\Traits\HasAddress;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use RuangDeveloper\LaravelSettings\Traits\HasSettings;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * School Model – Central Tenant Representation
 *
 * Purpose & Context:
 * ------------------
 * This model represents a single school (tenant/branch) in the multi-tenant SaaS application.
 * It serves as the primary scoping entity for almost all other models via the BelongsToSchool trait
 * and SchoolScope global scope.
 *
 * Key Features Implemented:
 * -------------------------
 * - UUID primary keys (HasUuids)
 * - Soft deletes with proper policy enforcement
 * - Polymorphic multi-address management via HasAddress trait (primary address + additional addresses)
 * - Spatie Media Library for logos, favicons, and dark-mode variants (single-file collections with fallbacks)
 * - Activity logging for audit trails
 * - Settings storage via HasSettings trait
 * - Advanced table querying (filtering, sorting, global search) via HasTableQuery, Filterable, Sortable
 * - Automatic unique slug generation on creation
 * - Extra data merging into JSON 'data' column for forward compatibility
 *
 * Integration Points:
 * -------------------
 * - Addresses: Uses the polymorphic HasAddress trait – primary address is automatically available via $school->primaryAddress()
 * - Media: Logos and favicons are stored in dedicated single-file collections with fallback URLs
 * - Relationships: Users (pivot), class levels (through sections), academic sessions, vehicles
 * - Frontend: Props like logo_url, primary address fields are automatically appended/serialized for Inertia
 *
 * Problems Solved:
 * ----------------
 * - Eliminates duplicated address columns/JSON storage – now uses proper polymorphic table
 * - Ensures consistent media handling with previews and fallbacks
 * - Provides clean, reusable accessors for frontend consumption
 * - Maintains data integrity with unique slugs and soft-delete safety
 */
class School extends \App\Models\Model implements HasMedia
{
    use HasFactory;
    use HasSettings;
    use HasUuids;
    use HasAddress;              // Polymorphic multi-address management (primary + additional)
    use BelongsToSections;
    use Filterable;
    use Sortable;
    use SoftDeletes;
    use InteractsWithMedia;
    use HasTableQuery;
    use LogsActivity;

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'name',
        'slug',
        'code',
        'email',
        'phone_one',
        'phone_two',
        'type',
        'is_active',
        'data',
    ];

    /**
     * Type casting.
     */
    protected $casts = [
        'data'      => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Accessors appended to model serialization (API/Inertia responses).
     */
    protected $appends = [
        'logo_url',
        'small_logo_url',
        'favicon_url',
        'dark_logo_url',
        'dark_small_logo_url',
    ];

    /**
     * Columns used for global search in AdvancedDataTable.
     */
    protected array $globalFilterFields = [
        'name',
        'email',
        'phone_one',
        'phone_two',
        'type',
        'code',
    ];

    /**
     * Columns hidden by default in table views (users can toggle visibility).
     */
    protected array $defaultHiddenColumns = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Columns completely hidden from frontend table (sensitive or unnecessary).
     */
    protected array $hiddenTableColumns = [
        'data',
    ];

    /**
     * Boot method – handles slug generation and extra data merging.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $school) {
            // Generate unique slug if not provided
            if (empty($school->slug)) {
                $baseSlug = Str::slug($school->name);
                $school->slug = $baseSlug;

                $counter = 1;
                while (static::withTrashed()->where('slug', $school->slug)->exists()) {
                    $school->slug = $baseSlug . '-' . $counter++;
                }
            }

            // Merge any non-fillable attributes into the JSON 'data' column
            $extra = array_diff_key(
                $school->getAttributes(),
                array_flip($school->getFillable())
            );

            $school->data = array_merge($school->data ?? [], $extra);
        });
    }

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    public function users()
    {
        return $this->belongsToMany(User::class, 'school_users');
    }

    public function classLevels()
    {
        return $this->hasManyThrough(ClassLevel::class, \App\Models\SchoolSection::class);
    }

    public function academicSessions()
    {
        return $this->hasMany(AcademicSession::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    // =================================================================
    // ACTIVITY LOGGING
    // =================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('school')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "School [{$this->name}] was {$eventName}");
    }

    // =================================================================
    // MEDIA LIBRARY (Spatie)
    // =================================================================

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->useFallbackUrl('/images/default-logo.png')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/svg+xml']);

        $this->addMediaCollection('small_logo')
            ->singleFile()
            ->useFallbackUrl('/images/default-small-logo.png')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/svg+xml']);

        $this->addMediaCollection('favicon')
            ->singleFile()
            ->useFallbackUrl('/images/default-favicon.ico')
            ->acceptsMimeTypes(['image/x-icon', 'image/png', 'image/svg+xml']);

        $this->addMediaCollection('dark_logo')
            ->singleFile()
            ->useFallbackUrl('/images/default-dark-logo.png')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/svg+xml']);

        $this->addMediaCollection('dark_small_logo')
            ->singleFile()
            ->useFallbackUrl('/images/default-dark-small-logo.png')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/svg+xml']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->nonQueued();
    }

    // =================================================================
    // ACCESSORS (Media URLs)
    // =================================================================

    public function getLogoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('logo', 'thumb') ?: $this->getFirstMediaUrl('logo');
    }

    public function getSmallLogoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('small_logo', 'thumb') ?: $this->getFirstMediaUrl('small_logo');
    }

    public function getFaviconUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('favicon');
    }

    public function getDarkLogoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('dark_logo', 'thumb') ?: $this->getFirstMediaUrl('dark_logo');
    }

    public function getDarkSmallLogoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('dark_small_logo', 'thumb') ?: $this->getFirstMediaUrl('dark_small_logo');
    }
}
