<?php

namespace App\Models\Academic;

use App\Models\Academic\AcademicSession;
use App\States\Academic\Term\Active as TermActive;
use App\States\Academic\Term\Closed as TermClosed;
use App\States\Academic\Term\Planned as TermPlanned;
use App\States\Academic\TermState;
use App\Traits\BelongsToSchool;
use App\Traits\HasDynamicEnum;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\ModelStates\HasStates;

/**
 * Term Model – Academic Term within a Session
 *
 * Lifecycle state is authoritative via Spatie Model States (planned → active → closed).
 * closed_at remains an audit field. DynamicEnum retains only name / short_name.
 * No is_active / is_closed lifecycle accessors — use state / state_label.
 */
class Term extends Model
{
    /** @use HasFactory<\Database\Factories\Academic\TermFactory> */
    use HasFactory, HasUuids, SoftDeletes, BelongsToSchool, HasTableQuery, LogsActivity, HasDynamicEnum, HasStates;

    protected $table = 'terms';

    protected $fillable = [
        'school_id',
        'academic_session_id',
        'name',
        'short_name',
        'ordinal_number',
        'description',
        'start_date',
        'end_date',
        'state',
        'closed_at',
        'color',
        'options',
    ];

    protected $casts = [
        'start_date'   => 'date:Y-m-d',
        'end_date'     => 'date:Y-m-d',
        'closed_at'    => 'datetime',
        'options'      => 'array',
        'state'        => TermState::class,
    ];

    public function getDynamicEnumProperties(): array
    {
        return [
            'name',
            'short_name',
        ];
    }

    protected array $hiddenTableColumns = [
        'school_id',
        'academic_session_id',
        'options',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected array $defaultHiddenColumns = [
        'description',
        'color',
        'closed_at',
    ];

    protected array $globalFilterFields = [
        'name',
        'short_name',
        'description',
        'color',
    ];

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('academic_session_id', $sessionId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('state', TermActive::$name);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('ordinal_number');
    }

    public function scopeNotClosed(Builder $query): Builder
    {
        return $query->where('state', '!=', TermClosed::$name);
    }

    public function getStateLabelAttribute(): string
    {
        return $this->state instanceof TermState
            ? $this->state->label()
            : ucfirst((string) $this->state);
    }

    public function canModifyStartDate(): bool
    {
        return $this->state instanceof TermPlanned;
    }

    public function isWithinSessionDates(AcademicSession $session): bool
    {
        if (! $this->start_date || ! $this->end_date) {
            return false;
        }

        return $this->start_date->gte($session->start_date)
            && $this->end_date->lte($session->end_date);
    }

    public function getPeriodAttribute(): ?string
    {
        if (! $this->start_date || ! $this->end_date) {
            return null;
        }

        return $this->start_date->format('M d, Y') . ' – ' .
               $this->end_date->format('M d, Y');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->short_name ?: $this->name;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('academic_term')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $eventName) => "Term \"{$this->display_name}\" ({$this->academicSession?->name}) was {$eventName}");
    }
}
