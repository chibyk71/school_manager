<?php

namespace App\Models\Academic;

use App\Traits\BelongsToSchool;
use App\Traits\BelongsToSections;
use App\Traits\HasConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TimeTable extends Model
{
    /** @use HasFactory<\Database\Factories\Academic\TimeTableFactory> */
    use HasFactory, LogsActivity, HasConfig, SoftDeletes, HasUuids, BelongsToSections, BelongsToSchool;

    protected $fillable = [
        'title',
        'term_id',
        'effective_date',
        'status',
        'school_id',
    ];

    protected $casts = [
        'effective_time' => 'datetime',
        'status' => 'boolean'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('time table')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
    
    public function getOption(string $option)
    {
        return array_get($this->options, $option);
    }

    /**
     * Get the term that owns the TimeTable
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function term()
    {
        return $this->belongsTo(Term::class, 'term_id', 'id');
    }
}
