<?php

namespace App\Models;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\Transport\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuangDeveloper\LaravelSettings\Traits\HasSettings;

class School extends Model
{
    /** @use HasFactory<\Database\Factories\SchoolFactory> */
    use HasFactory, HasSettings, HasUuids;

    protected $fillable = [];

    public function schoolSections()
    {
        return $this->hasMany(SchoolSection::class);
    }

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
