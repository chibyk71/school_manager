<?php

namespace App\Models\Calendar;

use App\Models\Academic\Term;
use App\Models\Configuration\EventType;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing an event in the school management system.
 *
 * Events are scoped to a school and term, with an associated event type.
 *
 * @property int $id Auto-incrementing primary key.
 * @property string|null $school_id Associated school ID.
 * @property int $event_type_id Foreign key to event_types table.
 * @property int $term_id Foreign key to terms table.
 * @property string $title Event title.
 * @property string|null $description Event description.
 * @property \Illuminate\Support\Carbon|null $start_date Event start date.
 * @property string|null $start_time Event start time (H:i format).
 * @property \Illuminate\Support\Carbon|null $end_date Event end date.
 * @property string|null $end_time Event end time (H:i format).
 * @property string|null $venue Event venue.
 * @property array|null $options Additional event options.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Event extends Model
{
    use HasFactory, LogsActivity, HasTableQuery, SoftDeletes, BelongsToSchool;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'school_id',
        'event_type_id',
        'term_id',
        'title',
        'description',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'venue',
        'options',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'options' => 'array',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = ['excerpt'];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'id',
        'school_id',
        'event_type_id',
        'term_id',
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
        'description',
        'venue',
    ];

    /**
     * Get the event type that owns the event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function eventType()
    {
        return $this->belongsTo(EventType::class);
    }

    /**
     * Get the term that owns the event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * Get the excerpt of the description.
     *
     * @return string|null
     */
    public function getExcerptAttribute(): ?string
    {
        return createExcerpt($this->description, 100);
    }

    /**
     * Get an option value from the options array.
     *
     * @param string $option
     * @return mixed
     */
    public function getOption(string $option)
    {
        return Arr::get($this->options, $option);
    }

    /**
     * Format the start_date attribute.
     *
     * @param mixed $value
     * @return string|null
     */
    public function getStartDateAttribute($value): ?string
    {
        return $value ? date('Y-m-d', strtotime($value)) : null;
    }

    /**
     * Format the end_date attribute.
     *
     * @param mixed $value
     * @return string|null
     */
    public function getEndDateAttribute($value): ?string
    {
        return $value ? date('Y-m-d', strtotime($value)) : null;
    }

    /**
     * Get the options for logging changes to the model.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('event')
            ->setDescriptionForEvent(function ($event) {
                return "Event {$event}: {$this->title}";
            })
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
