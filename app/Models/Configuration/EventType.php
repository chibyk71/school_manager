<?php

namespace App\Models\Configuration;

use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing an event type in the school management system.
 *
 * Event types are global reference data used by events across all schools.
 *
 * @property int $id Auto-incrementing primary key.
 * @property string $name Event type name (e.g., Meeting, Holiday).
 * @property string|null $description Event type description.
 * @property string|null $color Optional color (hex code, e.g., #FF0000).
 * @property array|null $options Additional options.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class EventType extends Model
{
    use HasFactory, LogsActivity, HasTableQuery, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'color',
        'options',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'options' => 'array',
    ];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'id',
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
        'name',
        'description',
    ];

    /**
     * Get the events associated with this event type.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function events()
    {
        return $this->hasMany(\App\Models\Calendar\Event::class, 'event_type_id');
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
     * Scope a query to filter by ID.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterById($query, $id)
    {
        if (!$id) {
            return $query;
        }
        return $query->where('id', '=', $id);
    }

    /**
     * Scope a query to filter by name.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $name
     * @param bool $strict
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterByName($query, $name, $strict = false)
    {
        if (!$name) {
            return $query;
        }
        return $strict ? $query->where('name', '=', $name) : $query->where('name', 'like', '%' . $name . '%');
    }

    /**
     * Get the options for logging changes to the model.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('event_type')
            ->setDescriptionForEvent(function ($event) {
                return "Event type {$event}: {$this->name}";
            })
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
