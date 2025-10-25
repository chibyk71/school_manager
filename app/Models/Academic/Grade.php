<?php

namespace App\Models\Academic;

use App\Models\SchoolSection;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Grade model representing a grading scale (e.g., A, B, C) within a school.
 *
 * @property int $id
 * @property int $school_id
 * @property int|null $school_section_id
 * @property string $name
 * @property string $code
 * @property int $min_score
 * @property int $max_score
 * @property string|null $remark
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Grade extends Model
{
    use HasFactory, BelongsToSchool, HasTableQuery, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'school_section_id',
        'name',
        'code',
        'min_score',
        'max_score',
        'remark',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'min_score' => 'integer',
        'max_score' => 'integer',
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
        'code',
        'remark',
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
            ->setDescriptionForEvent(fn(string $eventName) => "Grade {$this->name} ({$this->code}) was {$eventName}");
    }

    /**
     * Define the relationship to the SchoolSection model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function schoolSection(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SchoolSection::class, 'school_section_id');
    }

    /**
     * Scope a query to only include grades for a specific school section.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $schoolSectionId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForSchoolSection($query, int $schoolSectionId)
    {
        return $query->where('school_section_id', $schoolSectionId);
    }
}