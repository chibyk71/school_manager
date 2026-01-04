<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\Transport\Vehicle\Vehicle;
use App\Traits\HasAddress;
use App\Traits\BelongsToSections;
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
 * Model representing a school in a single-tenant system.
 */
class School extends \App\Models\Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\SchoolFactory> */
    use HasFactory, HasSettings, HasUuids, HasAddress, BelongsToSections, Filterable, Sortable, SoftDeletes, InteractsWithMedia, HasTableQuery, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'code',
        'email',
        'phone_one',
        'phone_two',
        'logo',
        'small_logo',
        'favicon',
        'dark_logo',
        'dark_small_logo',
        'type',
        'is_active',
        'data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'data' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'logo_url',
        'small_logo_url',
        'favicon_url',
        'dark_logo_url',
        'dark_small_logo_url',
    ];

    protected array $globalFilterFields = [
        'name',
        'email',
        'phone_one',
        'phone_two',
        'type',
        'code',
    ];

    protected array $defaultHiddenColumns = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected array $hiddenTableColumns = [
        'data',
        'dark_logo',
        'dark_small_logo',
        'favicon',
        'small_logo',
    ];

    /**
     * Boot the model and add event listeners.
     *
     * Automatically generates a slug and merges extra data during creation.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($school) {
            // Generate slug if not provided
            if (empty($school->slug)) {
                $school->slug = Str::slug($school->name);
                // Ensure slug uniqueness
                $baseSlug = $school->slug;
                $counter = 1;
                while (static::where('slug', $school->slug)->exists()) {
                    $school->slug = $baseSlug . '-' . $counter++;
                }
            }

            // Merge extra data into the data column
            $school->data = array_merge($school->data ?? [], array_diff_key($school->getAttributes(), array_flip($school->getFillable())));
        });
    }

    /**
     * The users (e.g., admins, staff) associated with the school.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'school_users');
    }

    /**
     * The class levels associated with the school through sections.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function classLevels()
    {
        return $this->hasManyThrough(ClassLevel::class, SchoolSection::class);
    }

    /**
     * The academic sessions associated with the school.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function academicSessions()
    {
        return $this->hasMany(AcademicSession::class);
    }

    /**
     * The vehicles associated with the school.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * The custom fields associated with the school.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function customFields()
    {
        return $this->hasMany(CustomField::class);
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
            ->setDescriptionForEvent(fn(string $eventName) => "school [{$this->name}] was {$eventName}");
    }

    /**
     * Register media collections for logos.
     */
    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('logo')
            ->singleFile() // Only one file per collection
            ->useFallbackUrl('/images/default-logo.png') // Default if none
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/svg+xml']); // Restrict types

        $this
            ->addMediaCollection('small_logo')
            ->singleFile()
            ->useFallbackUrl('/images/default-small-logo.png')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/svg+xml']);

        $this
            ->addMediaCollection('favicon')
            ->singleFile()
            ->useFallbackUrl('/images/default-favicon.ico')
            ->acceptsMimeTypes(['image/png', 'image/x-icon', 'image/svg+xml']);

        $this
            ->addMediaCollection('dark_logo')
            ->singleFile()
            ->useFallbackUrl('/images/default-dark-logo.png')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/svg+xml']);

        $this
            ->addMediaCollection('dark_small_logo')
            ->singleFile()
            ->useFallbackUrl('/images/default-dark-small-logo.png')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/svg+xml']);
    }

    /**
     * Register media conversions (e.g., thumbnails, optimizations).
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Example: Auto-resize logos
        $this->addMediaConversion('thumb')
            ->width(100)
            ->height(100)
            ->sharpen(10)
            ->format('png'); // Apply to all collections if needed
    }

    // Accessors for $appends (use Media Library URLs)
    public function getLogoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('logo') ?: null;
    }

    public function getSmallLogoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('small_logo') ?: null;
    }

    public function getFaviconUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('favicon') ?: null;
    }

    public function getDarkLogoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('dark_logo') ?: null;
    }

    public function getDarkSmallLogoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('dark_small_logo') ?: null;
    }
}
