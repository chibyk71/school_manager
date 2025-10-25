<?php

namespace App\Models\Academic;

use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\BelongsToSections;
use App\Traits\HasConfig;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Arr;

/**
 * TimeTable model representing a timetable for a term and school section.
 *
 * @property string $id
 * @property int $school_id
 * @property int $term_id
 * @property string $title
 * @property \Illuminate\Support\Carbon|null $effective_date
 * @property string $status
 * @property array|null $options
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class TimeTable extends Model
{
    use HasFactory, HasUuids, SoftDeletes, LogsActivity, HasConfig, BelongsToSchool, BelongsToSections, HasTableQuery;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'term_id',
        'title',
        'effective_date',
        'status',
        'options',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'effective_date' => 'datetime',
        'options' => 'array',
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
        'options',
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
        'title',
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
            ->useLogName('timetable')
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Timetable {$this->title} was {$eventName}");
    }

    /**
     * Get a specific option from the options array.
     *
     * @param string $option
     * @param mixed $default
     * @return mixed
     */
    public function getOption(string $option, $default = null)
    {
        return Arr::get($this->options, $option, $default);
    }

    /**
     * Define the relationship to the Term model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function term()
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    /**
     * Define the relationship to timetable slots.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function slots()
    {
        return $this->hasMany(TimeTableDetail::class, 'timetable_id');
    }

    /**
     * Scope a query to only include timetables for a specific term.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $termId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTerm($query, int $termId)
    {
        return $query->where('term_id', $termId);
    }

    /**
     * Scope a query to only include active timetables.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}