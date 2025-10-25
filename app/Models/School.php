<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\Transport\Vehicle\Vehicle;
use App\Traits\HasAddress;
use App\Traits\BelongsToSections;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use RuangDeveloper\LaravelSettings\Traits\HasSettings;

/**
 * Model representing a school in a single-tenant system.
 */
class School extends Model
{
    /** @use HasFactory<\Database\Factories\SchoolFactory> */
    use HasFactory, HasSettings, HasUuids, HasAddress, BelongsToSections, Filterable, Sortable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone_one',
        'phone_two',
        'logo',
        'type',
        'data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'data' => 'array',
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
}
