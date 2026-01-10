<?php

namespace App\Models\Academic;

use App\Models\Academic\AcademicSession;
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

/**
 * Term Model – Academic Term within a Session
 *
 * Represents a single academic term (e.g., First Term, Second Term) inside an AcademicSession.
 * This is the second-level time boundary in the academic calendar.
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Multi-tenant isolation via BelongsToSchool trait
 * • UUID primary keys for clean APIs
 * • Soft deletes + activity logging for audit compliance
 * • DynamicEnum integration for fully customizable status values per school
 * • Strict date validation support (enforced in service layer)
 * • Single active term per session enforcement support
 * • Reopen/closure logic preparation (is_active + is_closed flags planned)
 * • Ordered term retrieval via ordinal_number
 * • Optimized for DataTable usage (HasTableQuery trait)
 * • Comprehensive activity logging for academic record integrity
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Child entity of AcademicSession (belongsTo)
 * • Root boundary for term-specific operations:
 *   - Continuous assessments
 *   - Mid-term & end-of-term exams
 *   - Attendance tracking
 *   - Results publication per term
 * • Central to activation/closure workflows
 * • Works tightly with AcademicCalendarService for business rules
 *
 * Important Business Rules (mostly enforced in AcademicCalendarService):
 * • Only ONE term can be active per academic session at any time
 * • term.start_date ≥ parent_session.start_date
 * • term.end_date ≤ parent_session.end_date
 * • Once term becomes active → start_date becomes immutable
 * • End date can be adjusted while active
 * • Reopening closed term only allowed for the LAST closed term
 *   AND only if next term is still pending/upcoming
 * • Status is string → fully customizable via DynamicEnums trait
 *
 * Usage Examples:
 *   Term::active()->first();                        // Current active term
 *   $session->terms()->ordered()->get();            // All terms in correct order
 *   $term->isWithinSessionDates($session);          // Date validation helper
 *   $term->getStatusDisplayName();                  // DynamicEnum friendly
 */
class Term extends Model
{
    /** @use HasFactory<\Database\Factories\Academic\TermFactory> */
    use HasFactory, HasUuids, SoftDeletes, BelongsToSchool, HasTableQuery, LogsActivity, HasDynamicEnum;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'terms';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'academic_session_id',
        'name',
        'short_name',
        'ordinal_number',
        'description',
        'start_date',
        'end_date',
        'status',
        'is_active',
        'is_closed',
        'closed_at',
        'color',
        'options',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string|class-string>
     */
    protected $casts = [
        'start_date'   => 'date:Y-m-d',
        'end_date'     => 'date:Y-m-d',
        'closed_at'    => 'datetime',
        'options'      => 'array',
        'is_active'    => 'boolean',
        'is_closed'    => 'boolean',
    ];

    /**
     * DynamicEnum properties – which columns should use school-customizable options.
     *
     * @return array<string>
     */
    public function getDynamicEnumProperties(): array
    {
        return [
            'status',       // e.g. pending → active → closed (school can add 'midterm', 'revision' etc.)
            'name',         // e.g. First Term, Second Term, Third Term
            'short_name',   // e.g. 1st, 2nd, 3rd
        ];
    }

    /**
     * Default hidden columns in DataTable views (never sent to frontend).
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'school_id',
        'academic_session_id',
        'options',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Columns that are sent to frontend but hidden by default (user can toggle).
     *
     * @var array<string>
     */
    protected array $defaultHiddenColumns = [
        'description',
        'color',
        'closed_at',
    ];

    /**
     * Fields used for global free-text search in DataTables.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'name',
        'short_name',
        'description',
        'status',
        'color',
    ];

    // ────────────────────────────────────────────────────────────────
    // Status Constants (default values – can be overridden via DynamicEnums)
    // ────────────────────────────────────────────────────────────────

    public const STATUS_PENDING  = 'pending';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_CLOSED   = 'closed';

    public const DEFAULT_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACTIVE,
        self::STATUS_CLOSED,
    ];

    // ────────────────────────────────────────────────────────────────
    // Relationships
    // ────────────────────────────────────────────────────────────────

    /**
     * The parent academic session this term belongs to.
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    // ────────────────────────────────────────────────────────────────
    // Scopes
    // ────────────────────────────────────────────────────────────────

    /**
     * Scope: Only terms belonging to a specific academic session.
     */
    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('academic_session_id', $sessionId);
    }

    /**
     * Scope: Only the currently active term.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Ordered by ordinal_number (1st, 2nd, 3rd, etc.).
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('ordinal_number');
    }

    /**
     * Scope: Only non-closed terms.
     */
    public function scopeNotClosed(Builder $query): Builder
    {
        return $query->where('is_closed', false);
    }

    // ────────────────────────────────────────────────────────────────
    // Accessors & Helpers
    // ────────────────────────────────────────────────────────────────

    /**
     * Check if this term is currently active.
     */
    public function getIsActiveAttribute(): bool
    {
        return (bool) $this->attributes['is_active'] ?? false;
    }

    /**
     * Check if this term has been closed.
     */
    public function getIsClosedAttribute(): bool
    {
        return (bool) $this->attributes['is_closed'] ?? false;
    }

    /**
     * Determine if start_date can still be modified.
     * (Immutable after term becomes active)
     */
    public function canModifyStartDate(): bool
    {
        return ! $this->is_active && ! $this->is_closed;
    }

    /**
     * Check if the term dates are within the parent session's date range.
     * (Helper – final validation happens in service layer)
     */
    public function isWithinSessionDates(AcademicSession $session): bool
    {
        if (! $this->start_date || ! $this->end_date) {
            return false;
        }

        return $this->start_date->gte($session->start_date)
            && $this->end_date->lte($session->end_date);
    }

    /**
     * Get human-readable term period (e.g. "Sep 01 – Dec 15, 2025").
     */
    public function getPeriodAttribute(): ?string
    {
        if (! $this->start_date || ! $this->end_date) {
            return null;
        }

        return $this->start_date->format('M d, Y') . ' – ' .
               $this->end_date->format('M d, Y');
    }

    /**
     * Get the display name (short_name if available, else name).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->short_name ?: $this->name;
    }

    // ────────────────────────────────────────────────────────────────
    // Activity Logging Configuration
    // ────────────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('academic_term')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn(string $eventName) => "Term \"{$this->display_name}\" ({$this->academicSession?->name}) was {$eventName}");
    }
}
