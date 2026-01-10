<?php

namespace App\Models\Academic;

use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class AcademicSession
 *
 * Represents an academic session (school year) in a multi-tenant school management system.
 * Serves as the primary time boundary for all academic activities (terms, assessments, results, attendance, etc.).
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Multi-tenant isolation via BelongsToSchool trait
 * • UUID primary keys for clean APIs and distributed systems
 * • Soft deletes + activity logging for audit compliance
 * • Strict single active/current session enforcement support (via scopes & service)
 * • Date immutability logic hooks (start date locked after activation)
 * • Relationship to Terms (ordered by ordinal_number)
 * • Optimized for DataTable usage via HasTableQuery trait
 * • Comprehensive activity logging (critical for academic record integrity)
 * • Clear status progression support (draft → upcoming → active → closed → archived)
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Root entity — all terms, assessments, promotions, etc., belong to a session
 * • Central point for activation/closure workflows
 * • Provides integration point for future events (e.g., SessionActivatedEvent, SessionClosedEvent)
 * • Works with AcademicCalendarService for business rule enforcement
 *
 * Important Business Rules (enforced mostly in AcademicCalendarService):
 * • Only ONE session can be is_current = true per school
 * • Once status becomes 'active', start_date becomes immutable
 * • End date can still be adjusted while active
 * • Status progression: draft → upcoming → active → closed → archived
 * • Soft-deleted sessions remain available for historical queries/reports
 *
 * Usage Examples:
 *   AcademicSession::current()->first();                    // Get active session
 *   $session->terms()->ordered()->get();                    // All terms in order
 *   $session->isActive();                                   // Helper check
 *   $session->canModifyStartDate();                         // Immutability check
 */
class AcademicSession extends Model
{
    /** @use HasFactory<\Database\Factories\Academic\AcademicSessionFactory> */
    use HasFactory, HasUuids, SoftDeletes, BelongsToSchool, HasTableQuery, LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'academic_sessions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_current',
        'status',
        'activated_at',
        'closed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date'    => 'date:Y-m-d',
        'end_date'      => 'date:Y-m-d',
        'is_current'    => 'boolean',
        'activated_at'  => 'datetime',
        'closed_at'     => 'datetime',
        'status'        => 'string',
    ];

    /**
     * The attributes that should be hidden for arrays/JSON.
     *
     * @var array<string>
     */
    protected $hidden = [
        'school_id',
    ];

    /**
     * Default hidden columns in DataTable views (via HasTableQuery).
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'school_id',
        'deleted_at',
        'created_at',           // Usually hidden unless audit needed
    ];

    /**
     * Default hidden columns that are sent but initially hidden in UI.
     *
     * @var array<string>
     */
    protected array $defaultHiddenColumns = [
        'activated_at',
        'closed_at',
    ];

    /**
     * Fields used for global free-text search in DataTables.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'name',
    ];

    // ────────────────────────────────────────────────────────────────
    // Status Constants (align with migration + DynamicEnums possible)
    // ────────────────────────────────────────────────────────────────

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_UPCOMING  = 'upcoming';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_CLOSED    = 'closed';
    public const STATUS_ARCHIVED  = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_UPCOMING,
        self::STATUS_ACTIVE,
        self::STATUS_CLOSED,
        self::STATUS_ARCHIVED,
    ];

    // ────────────────────────────────────────────────────────────────
    // Relationships
    // ────────────────────────────────────────────────────────────────

    /**
     * Get all terms belonging to this session, ordered by ordinal number.
     */
    public function terms(): HasMany
    {
        return $this->hasMany(Term::class, 'academic_session_id')
            ->orderBy('ordinal_number');
    }

    // ────────────────────────────────────────────────────────────────
    // Scopes
    // ────────────────────────────────────────────────────────────────

    /**
     * Scope a query to only include the current (active) session.
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope a query to only include active sessions (status = active).
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope a query to only include non-archived sessions.
     */
    public function scopeNotArchived($query)
    {
        return $query->where('status', '!=', self::STATUS_ARCHIVED);
    }

    // ────────────────────────────────────────────────────────────────
    // Accessors & Mutators
    // ────────────────────────────────────────────────────────────────

    /**
     * Check if this session is currently active.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Determine if the start date can still be modified.
     * (Immutable after activation)
     */
    public function canModifyStartDate(): bool
    {
        return ! $this->isActive && $this->status !== self::STATUS_CLOSED;
    }

    /**
     * Get the duration of the session in human-readable format.
     */
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

    // ────────────────────────────────────────────────────────────────
    // Activity Logging (Spatie)
    // ────────────────────────────────────────────────────────────────

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
