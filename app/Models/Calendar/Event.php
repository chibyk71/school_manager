<?php

namespace App\Models\Calendar;

use App\Models\Academic\Term;
use App\Models\Configuration\EventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Event extends Model
{
    /** @use HasFactory<\Database\Factories\Calendar\EventFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'event_type_id',
        'term_id',
        'title',
        'description',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'venue',
        'options'
    ];

    protected $appends = ['excerpt'];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'options' => 'array',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    /**
     * Get the eventType that owns the Event
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function eventType()
    {
        return $this->belongsTo(EventType::class);
    }

    /**
     * Get the term that owns the Event
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('event_type')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }

    public function getExcerptAttribute()
    {
        return createExcerpt($this->description, 100);
    }

    public function getOption(string $option)
    {
        return Arr::get($this->options, $option);
    }
    
    public function getStartDateAttribute($value)
    {
        return $value ? date('Y-m-d', strtotime($value)) : null;
    }

    public function getEndDateAttribute($value)
    {
        return $value ? date('Y-m-d', strtotime($value)) : null;
    }
}
