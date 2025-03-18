<?php

namespace App\Models;

use App\Models\Academic\ClassLevel;
use App\Models\Employee\Staff;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

//? this model could also be used as the team model

class SchoolSection extends Model
{
    /** @use HasFactory<\Database\Factories\SchoolSectionFactory> */
    use HasFactory, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'name',
        'description',
        'display_name',
    ];

    /**
     * The staffs that belong to the SchoolSection
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function staffs()
    {
        return $this->belongsToMany(Staff::class, 'staff_school_setion _pivot');
    }

    public function classLevels()
    {
        return $this->hasMany(ClassLevel::class);
    }

    public function students() {
        return $this->hasManyThrough(Student::class, ClassLevel::class);
    }
}
