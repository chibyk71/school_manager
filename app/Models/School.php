<?php

namespace App\Models;

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
}
