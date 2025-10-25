<?php

namespace App\Models\Academic;

use App\Models\School;
use App\Models\SchoolSection;
use App\Traits\BelongsToSchool;
use App\Traits\BelongsToSections;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * ClassLevel model representing academic class levels (e.g., JSS1, SSS2) within a school.
 *
 * @property int $id
 * @property int $school_id
 * @property int $school_section_id
 * @property string $name
 * @property string|null $display_name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class ClassLevel extends Model
{
    use HasFactory, BelongsToSchool, BelongsToSections, HasTableQuery, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'school_section_id',
        'school_id', // Included for explicit assignment in multi-tenancy
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'created_at',
        'updated_at',
        'deleted_at',
        'school_id', // Hidden from frontend but used internally for scoping
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'name',
        'display_name',
        'description',
    ];

    /**
     * Configure activity logging options.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable() // Log changes to fillable attributes
            ->logOnlyDirty() // Only log changed attributes
            ->setDescriptionForEvent(fn(string $eventName) => "ClassLevel {$this->name} was {$eventName}");
    }

    /**
     * Define the relationship to the SchoolSection model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function schoolSection()
    {
        return $this->belongsTo(SchoolSection::class, 'school_section_id');
    }

    /**
     * Define the relationship to ClassSection models.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function classSections()
    {
        return $this->hasMany(ClassSection::class, 'class_level_id');
    }

    /**
     * Scope a query to only include class levels for the active school.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|null $schoolId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSchool($query, $schoolId = null)
    {
        $schoolId = $schoolId ?? GetSchoolModel()?->id;
        if (!$schoolId) {
            throw new \Exception('No active school found.');
        }
        return $query->where('school_id', $schoolId);
    }
}