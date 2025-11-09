<?php

namespace App\Models\Exam;

use App\Models\Academic\Term;
use App\Models\Model;
use App\Models\SchoolSection;
use App\Traits\BelongsToSchool;
use App\Traits\BelongsToSections;
use App\Traits\HasConfig;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing an assessment (e.g., exam, quiz) in the school management system.
 *
 * @property string $id UUID primary key
 * @property string|null $school_id UUID of the associated school
 * @property string $assessment_type_id UUID of the assessment type
 * @property string $term_id ID of the academic term
 * @property string $name Assessment name
 * @property int $weight Assessment weight
 * @property int $max_score Maximum score for the assessment
 * @property \Illuminate\Support\Carbon $date_effective Start date for the assessment
 * @property \Illuminate\Support\Carbon $date_due End date for the assessment
 * @property \Illuminate\Support\Carbon $published_at Date when results are published
 * @property string|null $instruction Assessment instructions
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read string $type Assessment type name
 */
class Assessment extends Model
{
    use LogsActivity, BelongsToSchool, BelongsToSections, HasTableQuery, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'assessment_type_id',
        'school_id',
        'term_id',
        'name',
        'weight',
        'max_score',
        'date_effective',
        'date_due',
        'published_at',
        'instruction',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_effective' => 'date',
        'date_due' => 'date',
        'published_at' => 'datetime',
    ];

    /**
     * The attributes used for global filtering.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = ['name', 'instruction'];

    /**
     * The attributes hidden from table queries.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = ['school_id', 'created_at', 'updated_at'];

    /**
     * Define the relationship with the AssessmentType model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function assessmentType()
    {
        return $this->belongsTo(AssessmentType::class);
    }

    /**
     * Define the relationship with the Term model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * Get the activity log options for the model.
     *
     * @return LogOptions
     * @throws \Exception If configuration name is not defined.
     */
    public function getActivitylogOptions(): LogOptions
    {
        $logName = $this->configs()->name ?? 'assessment';
        return LogOptions::defaults()
            ->useLogName($logName)
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
