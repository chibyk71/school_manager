<?php

namespace App\Models\Academic;

use App\Models\School;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * ClassSection model representing a specific section (e.g., JSS1-A) within a class level.
 *
 * @property int $id
 * @property int $school_id
 * @property int $class_level_id
 * @property string $name
 * @property string|null $room
 * @property int $capacity
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read int $no_students
 */
class ClassSection extends Model
{
    use HasFactory, BelongsToSchool, HasTableQuery, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'class_level_id',
        'name',
        'room',
        'capacity',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'capacity' => 'integer',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'no_students',
    ];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'school_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'name',
        'room',
        'status',
    ];

    /**
     * Configure activity logging options.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "ClassSection {$this->name} was {$eventName}");
    }

    /**
     * Get the ClassLevel that owns the ClassSection.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class, 'class_level_id');
    }

    /**
     * The students that belong to the ClassSection.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_class_section_pivot');
    }

    /**
     * Get the number of students in the ClassSection.
     *
     * @return int
     */
    public function getNoStudentsAttribute(): int
    {
        return $this->students()->count();
    }

    /**
     * Scope a query to only include class sections for a specific class level.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $classLevelId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForClassLevel($query, int $classLevelId)
    {
        return $query->where('class_level_id', $classLevelId);
    }
}