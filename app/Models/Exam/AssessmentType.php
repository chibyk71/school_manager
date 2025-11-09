<?php

namespace App\Models\Exam;

use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing an assessment type (e.g., quiz, midterm, final) in the school management system.
 *
 * @property string $id Primary key
 * @property string $school_id UUID of the associated school
 * @property string $name Assessment type name
 * @property string|null $description Assessment type description
 * @property string $status Assessment type status (e.g., active, inactive)
 * @property int $weight Assessment type weight
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Exam\Assessment[] $assessments
 */
class AssessmentType extends Model
{
    use BelongsToSchool, HasTableQuery, LogsActivity, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'assessment_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'description',
        'status',
        'weight',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'weight' => 'integer',
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
    protected array $hiddenTableColumns = ['school_id', 'created_at', 'updated_at'];

    /**
     * Define the relationship with the Assessment model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

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
            ->useLogName('assessment_type');
    }
}
