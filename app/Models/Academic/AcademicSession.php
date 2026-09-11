<?php

namespace App\Models\Academic;

use App\Models\Model;
use App\States\Academic\AcademicSession\Active;
use App\States\Academic\AcademicSession\Closed;
use App\States\Academic\AcademicSessionState;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\ModelStates\HasStates;

class AcademicSession extends Model
{
    /** @use HasFactory<\Database\Factories\Academic\AcademicSessionFactory> */
    use HasFactory, HasUuids, SoftDeletes, BelongsToSchool, HasTableQuery, LogsActivity, HasStates;

    protected $table = 'academic_sessions';

    protected $fillable = [
        'school_id',
        'name',
        'start_date',
        'end_date',
        'state',
        'activated_at',
        'closed_at',
    ];

    protected $casts = [
        'start_date'    => 'date:Y-m-d',
        'end_date'      => 'date:Y-m-d',
        'activated_at'  => 'datetime',
        'closed_at'     => 'datetime',
        'state'         => AcademicSessionState::class,
    ];

    protected $hidden = [
        'school_id',
    ];

    protected array $hiddenTableColumns = [
        'school_id',
        'deleted_at',
        'created_at',
    ];

    protected array $defaultHiddenColumns = [
        'activated_at',
        'closed_at',
    ];

    protected array $globalFilterFields = [
        'name',
    ];

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class, 'academic_session_id')
            ->orderBy('ordinal_number');
    }

    /**
     * Scope: sessions in the ACTIVE lifecycle state.
     */
    public function scopeActive($query)
    {
        return $query->where('state', Active::$name);
    }

    /**
     * Scope: sessions that are not closed.
     */
    public function scopeNotClosed($query)
    {
        return $query->where('state', '!=', Closed::$name);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->state instanceof Active;
    }

    public function getStateLabelAttribute(): string
    {
        return $this->state instanceof AcademicSessionState
            ? $this->state->label()
            : ucfirst((string) $this->state);
    }

    public function canModifyStartDate(): bool
    {
        return ! ($this->state instanceof Active) && ! ($this->state instanceof Closed);
    }

    public function getDurationAttribute(): ?string
    {
        if (! $this->start_date || ! $this->end_date) {
            return null;
        }

        $days = $this->start_date->diffInDays($this->end_date) + 1;

        return $this->start_date->format('M d, Y') . ' - ' .
               $this->end_date->format('M d, Y') .
               " ({$days} days)";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('academic_session')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn(string $eventName) => "Academic session \"{$this->name}\" has been {$eventName}");
    }
}
