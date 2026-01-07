<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\Student;
use App\Models\Employee\Staff;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laratrust\Models\Team;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing a school section (e.g., primary, secondary).
 */
class SchoolSection extends Team
{
    /** @use HasFactory<\Database\Factories\SchoolSectionFactory> */
    use HasFactory, BelongsToSchool, HasTableQuery, LogsActivity, HasUuids, Filterable, Sortable;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'school_id',
        'name',
        'description',
        'display_name',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'school_id' => 'string',
    ];

/**
     * The attributes used for global filtering.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = ['name', 'description'];

    /**
     * The attributes hidden from table queries.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = ['school_id'];

    protected array $defaultHiddenColumns = ['created_at', 'updated_at'];

    /**
     * Get the activity log options for the model.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->useLogName('school_section');
    }

    /**
     * The staff members associated with the school section.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function staffs()
    {
        return $this->belongsToMany(Staff::class, 'staff_school_section_pivot');
    }

    /**
     * The class levels belonging to the school section.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function classLevels()
    {
        return $this->hasMany(ClassLevel::class);
    }

    /**
     * The students associated with the school section through class levels.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function students()
    {
        return $this->hasManyThrough(Student::class, ClassLevel::class);
    }
}
