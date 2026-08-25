<?php

namespace App\Models\Academic;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * TimetableConflict — A lesson assignment that the auto-generator could not place.
 *
 * When the TimetableGeneratorService cannot assign a subject-teacher pair to any
 * available period without creating a scheduling conflict, it writes a record here
 * instead of silently skipping the assignment or forcing an invalid placement.
 *
 * ── What Each Row Represents ─────────────────────────────────────────────────
 * "I tried to schedule SSS 1A's Biology lesson (Mr. Abubakar) but he is already
 *  teaching SSS 2B Chemistry during every available period on every working day.
 *  Here are 3 alternative slots that might work if admin moves something around."
 *
 * ── Conflict Types ────────────────────────────────────────────────────────────
 *
 *   teacher_double_booked
 *     The teacher is already assigned to a different section at the only
 *     available slot(s) for this subject. Generator exhausted all periods/days.
 *
 *   section_double_booked
 *     The class section already has a subject in every available lesson period.
 *     Usually indicates too many subjects for the available periods — admin needs
 *     to either reduce subjects, add periods, or accept that some won't fit.
 *
 *   no_available_period
 *     All lesson periods on all working days are occupied for this section.
 *     Similar to section_double_booked but at the schedule level.
 *
 *   no_teacher_assigned
 *     A subject in the class section has no row in teacher_class_section_subjects.
 *     Admin must assign a teacher before this subject can be scheduled.
 *     suggested_alternatives will be empty in this case.
 *
 *   frequency_unmet
 *     The generator placed some but not all required periods_per_week occurrences.
 *     The description field includes how many were placed vs how many were needed.
 *     Example: "Placed 3/5 required Biology periods for SSS 1A."
 *
 * ── Resolution Workflow ───────────────────────────────────────────────────────
 * 1. Generator creates conflict rows after a generation run.
 * 2. Admin sees them in the Conflicts panel (ConflictPanel.vue).
 * 3. Admin either:
 *    (a) Picks a suggested alternative → calls resolveWithSuggestion()
 *    (b) Manually drags a slot on the grid → calls resolveManually()
 *    (c) Assigns a missing teacher first (for no_teacher_assigned type)
 *        then re-generates or manually resolves.
 * 4. After all conflicts are resolved, admin can activate the timetable.
 *
 * ── History Preservation ──────────────────────────────────────────────────────
 * Conflict rows are never deleted. Resolved rows (resolved_at IS NOT NULL)
 * serve as an analytics trail: which subjects always conflict, which teachers
 * are overloaded, how long conflicts go unresolved.
 *
 * ── Properties ───────────────────────────────────────────────────────────────
 * @property int              $id
 * @property string           $school_id
 * @property string           $timetable_id
 * @property string|null      $class_section_id
 * @property int|null         $teacher_class_section_subject_id
 * @property int|null         $class_period_id
 * @property int|null         $day_of_week
 * @property string           $conflict_type
 * @property string|null      $description
 * @property array|null       $suggested_alternatives
 * @property \Carbon\Carbon|null $resolved_at
 * @property int|null         $resolved_by
 * @property string|null      $resolution_notes
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 *
 * ── Relationships ────────────────────────────────────────────────────────────
 * @property-read Timetable                          $timetable
 * @property-read ClassSection|null                  $classSection
 * @property-read TeacherClassSectionSubject|null    $assignment
 * @property-read ClassPeriod|null                   $period
 * @property-read \App\Models\User|null              $resolvedBy
 */
class TimetableConflict extends Model
{
    use HasFactory;
    use BelongsToSchool;
    use LogsActivity;

    // No SoftDeletes — conflicts are a permanent audit trail.
    // No HasTableQuery — conflicts are not shown in a standalone DataTable;
    // they are displayed in the ConflictPanel component, filtered by timetable_id.

    protected $table = 'timetable_conflicts';

    protected $fillable = [
        'school_id',
        'timetable_id',
        'class_section_id',
        'teacher_class_section_subject_id',
        'class_period_id',
        'day_of_week',
        'conflict_type',
        'description',
        'suggested_alternatives',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'teacher_class_section_subject_id' => 'integer',
        'class_period_id'                  => 'integer',
        'day_of_week'                      => 'integer',
        'suggested_alternatives'           => 'array',
        'resolved_at'                      => 'datetime',
        'created_at'                       => 'datetime',
        'updated_at'                       => 'datetime',
    ];

    // ── Conflict Type Constants ───────────────────────────────────────────────

    const TYPE_TEACHER_DOUBLE_BOOKED = 'teacher_double_booked';
    const TYPE_SECTION_DOUBLE_BOOKED = 'section_double_booked';
    const TYPE_NO_AVAILABLE_PERIOD   = 'no_available_period';
    const TYPE_NO_TEACHER_ASSIGNED   = 'no_teacher_assigned';
    const TYPE_FREQUENCY_UNMET       = 'frequency_unmet';

    // ── Activity Logging ──────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('timetable_conflict')
            ->logOnly(['resolved_at', 'resolved_by', 'resolution_notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn (string $eventName) =>
                    "Timetable conflict ({$this->conflict_type}) for timetable " .
                    "\"{$this->timetable?->title}\" was {$eventName}"
            );
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * The timetable this conflict belongs to.
     */
    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class, 'timetable_id');
    }

    /**
     * The class section arm that couldn't be scheduled.
     * Null for school-level conflicts (e.g. no_teacher_assigned with no arm context).
     */
    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    /**
     * The teacher-subject assignment that couldn't be placed.
     * Null for no_teacher_assigned conflicts (there is no assignment yet).
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(
            TeacherClassSectionSubject::class,
            'teacher_class_section_subject_id'
        );
    }

    /**
     * The specific period that triggered the conflict, when applicable.
     * Null for no_available_period (all periods failed).
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(ClassPeriod::class, 'class_period_id');
    }

    /**
     * The admin user who resolved this conflict.
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'resolved_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Only unresolved conflicts (resolved_at IS NULL).
     * Used by the conflict panel and activation pre-check.
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Only resolved conflicts (resolved_at IS NOT NULL).
     * Used for history/analytics views.
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
    }

    /**
     * Filter by conflict type.
     *
     * Example: $timetable->conflicts()->ofType(TimetableConflict::TYPE_NO_TEACHER_ASSIGNED)
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('conflict_type', $type);
    }

    /**
     * Filter conflicts for a specific class section.
     */
    public function scopeForSection(Builder $query, string $classSectionId): Builder
    {
        return $query->where('class_section_id', $classSectionId);
    }

    // ── State Helpers ─────────────────────────────────────────────────────────

    /**
     * Whether this conflict has been resolved by an admin.
     */
    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Whether this conflict is still pending resolution.
     */
    public function isPending(): bool
    {
        return $this->resolved_at === null;
    }

    /**
     * Human-readable label for the conflict type.
     * Used in the conflict panel UI.
     */
    public function getConflictTypeLabelAttribute(): string
    {
        return match ($this->conflict_type) {
            self::TYPE_TEACHER_DOUBLE_BOOKED => 'Teacher Double-Booked',
            self::TYPE_SECTION_DOUBLE_BOOKED => 'Section Double-Booked',
            self::TYPE_NO_AVAILABLE_PERIOD   => 'No Available Period',
            self::TYPE_NO_TEACHER_ASSIGNED   => 'No Teacher Assigned',
            self::TYPE_FREQUENCY_UNMET       => 'Frequency Not Met',
            default                          => ucwords(str_replace('_', ' ', $this->conflict_type)),
        };
    }

    /**
     * Severity level for UI badge colouring.
     * Returns a PrimeVue severity string.
     */
    public function getSeverityAttribute(): string
    {
        return match ($this->conflict_type) {
            self::TYPE_NO_TEACHER_ASSIGNED   => 'danger',  // Blocks resolution entirely
            self::TYPE_TEACHER_DOUBLE_BOOKED => 'warn',    // Requires manual rescheduling
            self::TYPE_SECTION_DOUBLE_BOOKED => 'warn',
            self::TYPE_NO_AVAILABLE_PERIOD   => 'warn',
            self::TYPE_FREQUENCY_UNMET       => 'info',    // Partial — less critical
            default                          => 'warn',
        };
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    /**
     * Mark this conflict as resolved.
     *
     * Called by TimetableService::resolveConflict() after a TimetableSlot
     * has been successfully created for this conflict. Should not be called
     * directly — always go through the service to ensure the slot is created
     * atomically alongside the resolution.
     *
     * @param int         $resolvedByUserId  The admin who resolved it.
     * @param string|null $notes             Optional explanation note.
     */
    public function markResolved(int $resolvedByUserId, ?string $notes = null): bool
    {
        return $this->update([
            'resolved_at'      => now(),
            'resolved_by'      => $resolvedByUserId,
            'resolution_notes' => $notes,
        ]);
    }

    /**
     * Get the suggested alternative at a given index.
     * Returns null if no suggestions exist or index is out of range.
     *
     * Each suggestion has the shape:
     * [
     *   'day_of_week'                      => int,
     *   'class_period_id'                  => int,
     *   'teacher_class_section_subject_id' => int,
     *   'score'                            => float,  // generator confidence 0..1
     *   'reason'                           => string,
     * ]
     *
     * @param int $index
     * @return array|null
     */
    public function getSuggestion(int $index): ?array
    {
        return $this->suggested_alternatives[$index] ?? null;
    }

    /**
     * Whether this conflict has any generator suggestions for admin to pick from.
     */
    public function hasSuggestions(): bool
    {
        return ! empty($this->suggested_alternatives);
    }

    /**
     * Whether this conflict can be auto-resolved (it has suggestions AND the
     * conflict type is not no_teacher_assigned, which requires external action).
     */
    public function canAutoResolve(): bool
    {
        return $this->hasSuggestions()
            && $this->conflict_type !== self::TYPE_NO_TEACHER_ASSIGNED;
    }
}
