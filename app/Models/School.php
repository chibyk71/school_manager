<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\Transport\Vehicle\Vehicle;
use App\Traits\BelongsToSections;
use App\Traits\HasAddress;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use RuangDeveloper\LaravelSettings\Traits\HasSettings;

class School extends Model
{
    /** @use HasFactory<\Database\Factories\SchoolFactory> */
    use HasFactory, HasSettings, HasUuids, HasAddress, BelongsToSections, Filterable, Sortable;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone_one',
        'phone_two',
        'logo',
        'data',
    ];

    // TODO: i want to make it that when create the model, the slug will be generated automatically, if not already present, and any extra data passed will be stored in the data column

    protected $casts = [
        'data' => 'array',
    ];

    public function classLevels()
    {
        return $this->hasManyThrough(ClassLevel::class, SchoolSection::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'school_users');
    }

    /**
     * Get all of the academic Sessions for the School
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function academicSessions()
    {
        return $this->hasMany(AcademicSession::class);
    }

    /**
     * Get all of the vehicles for the School
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }
}
