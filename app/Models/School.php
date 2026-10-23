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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laratrust\Models\Team;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ModelStates\HasStates;
use App\States\School\SchoolState;
use App\States\School\Draft;

/**
 * School Model – Central Tenant Representation + Laratrust Team
 *
 * Purpose & Context:
 * ------------------
 * This model represents a single school (tenant/branch) in the multi-tenant SaaS application.
 * It serves as the primary scoping entity for almost all other models via the BelongsToSchool trait
 * and SchoolScope global scope.
 *
 * Phase 1 Authorization:
 * ----------------------
 * School is the Laratrust Team. Authorization pivots (role_user, permission_user) use
 * school_id as the team foreign key. Generic authorization scopes are:
 *   - Tenant / global: school_id = NULL
 *   - School:          school_id = specific school
 * SchoolSection is NOT a generic authorization scope.
 *
 * Key Features Implemented:
 * -------------------------
 * - UUID primary keys (HasUuids)
 * - Soft deletes with proper policy enforcement
 * - Polymorphic multi-address management via HasAddress trait (primary address + additional addresses)
 * - Spatie Media Library for logos, favicons, and dark-mode variants (single-file collections with fallbacks)
 * - Activity logging for audit trails
 * - Settings: OI Laravel Settings infrastructure (Phase 1+)
 * - Advanced table querying (filtering, sorting, global search) via HasTableQuery, Filterable, Sortable
 * - Automatic unique slug generation on creation
 * - Extra data merging into JSON 'data' column for forward compatibility
 *
 * Integration Points:
 * -------------------
 * - Addresses: Uses the polymorphic HasAddress trait – primary address is automatically available via $school->primaryAddress()
 * - Media: Logos and favicons are stored in dedicated single-file collections with fallback URLs
 * - Relationships: Users (pivot), class levels (through sections), academic sessions, vehicles, dynamic enums
 * - Frontend: Props like logo_url, primary address fields are automatically appended/serialized for Inertia
 *
 * Problems Solved:
 * ----------------
 * - Eliminates duplicated address columns/JSON storage – now uses proper polymorphic table
 * - Ensures consistent media handling with previews and fallbacks
 * - Provides clean, reusable accessors for frontend consumption
 * - Maintains data integrity with unique slugs and soft-delete safety
 */
class School extends Team implements HasMedia
{
    // Polymorphic multi-address management (primary + additional)
    use BelongsToSections;
    use Filterable;
    use HasAddress;
    use HasFactory;
    use HasTableQuery;
    use HasUuids;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;
    use Sortable;
    use HasStates;

    /**
     * Explicit table so Laratrust Team constructor resolution cannot drift.
     *
     * @var string
     */
    protected $table = 'schools';

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
        'contact_name',
        'type',
        'description',
        'motto',
        'is_active',
        'state',
        'data',
    ];

    /**
     * Type casting.
     */
    protected $casts = [
        'data' => 'array',
        'is_active' => 'boolean',
        'state' => SchoolState::class,
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

        // Phase 1 lifecycle adds schools.state. Focused unit schemas that predate
        // that column must still be able to insert School rows without failing.
        static::saving(function (self $school): void {
            if (! array_key_exists('state', $school->getAttributes())) {
                return;
            }
            try {
                if (! Schema::hasColumn($school->getTable(), 'state')) {
                    unset($school->attributes['state']);
                }
            } catch (\Throwable) {
                unset($school->attributes['state']);
            }
        });

        // File Manager Phase 1: cascade soft-delete school-owned providers on deleted.
        // Mark each with deleted_via_school_cascade = true as explicit provenance
        // (not timestamp equality). Force-delete uses FK cascadeOnDelete.
        static::deleted(function (self $school): void {
            if ($school->isForceDeleting()) {
                return;
            }

            StorageProvider::query()
                ->where('ownership', 'school')
                ->where('school_id', $school->id)
                ->get()
                ->each(function (StorageProvider $provider): void {
                    $provider->delete();
                    StorageProvider::withTrashed()
                        ->whereKey($provider->id)
                        ->update(['deleted_via_school_cascade' => true]);
                });
        });

        // Restore only providers explicitly marked by the cascade. Independently
        // trashed providers (even with the same deleted_at second) stay trashed.
        // Ownership / school_id never change; flag is cleared on restore.
        static::restoring(function (self $school): void {
            StorageProvider::onlyTrashed()
                ->where('ownership', 'school')
                ->where('school_id', $school->id)
                ->where('deleted_via_school_cascade', true)
                ->get()
                ->each(function (StorageProvider $provider): void {
                    $provider->restore();
                    StorageProvider::query()
                        ->whereKey($provider->id)
                        ->update(['deleted_via_school_cascade' => false]);
                });
        });

        static::creating(function (self $school) {
            // Generate unique slug if not provided
            if (empty($school->slug)) {
                $baseSlug = Str::slug($school->name);
                $school->slug = $baseSlug;

                $counter = 1;
                while (static::withTrashed()->where('slug', $school->slug)->exists()) {
                    $school->slug = $baseSlug.'-'.$counter++;
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
    // LARATRUST TEAM
    // =================================================================

    /**
     * Foreign key Laratrust uses on role_user / permission_user pivots.
     * Must match config/laratrust.php foreign_keys.team.
     */
    public static function modelForeignKey(): string
    {
        return 'school_id';
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
        return $this->hasManyThrough(ClassLevel::class, SchoolSection::class);
    }

    public function academicSessions()
    {
        return $this->hasMany(AcademicSession::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function dynamicEnums()
    {
        return $this->hasMany(DynamicEnum::class);
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
            ->setDescriptionForEvent(fn (string $eventName) => "School [{$this->name}] was {$eventName}");
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
    // =================================================================
    // LIFECYCLE (Phase 1)
    // =================================================================

    public function getStateLabelAttribute(): string
    {
        return $this->state instanceof SchoolState
            ? $this->state->label()
            : ucfirst((string) $this->state);
    }

    public function scopeCountedForCapacity($query)
    {
        return $query->whereIn('state', \App\Services\School\SchoolCapacityService::countedStateNames());
    }
}
