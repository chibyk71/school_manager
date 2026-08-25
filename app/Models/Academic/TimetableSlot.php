<?php

namespace App\Models\Academic;

use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * TimetableSlot — A single lesson assignment within a timetable.
 *
 * One TimetableSlot answers five questions simultaneously:
 *   WHICH timetable?         → timetable_id
 *   WHICH class section?     → class_section_id
 *   WHICH period of the day? → class_period_id
 *   WHICH day of the week?   → day_of_week
 *   WHO teaches WHAT?        → teacher_class_section_subject_id
 *
 * ── Real-World Example ───────────────────────────────────────────────────────
 * "On Wednesday, Period 3 (10:20–11:00), SSS 1A has Biology
 *  taught by Mr. Abubakar."
 *
 * This is one TimetableSlot row:
 *   timetable_id                     = uuid of "SSS Term 1 2025/2026"
 *   class_section_id                 = uuid of "SSS 1A"
 *   class_period_id                  = id of "Period 5" in the Regular Day schedule
 *   teacher_class_section_subject_id = id of (Mr. Abubakar → SSS 1A → Biology)
 *   day_of_week                      = 3 (Wednesday)
 *
 * ── The `teacher_class_section_subject_id` FK Explained ──────────────────────
 * Rather than storing teacher_id, subject_id, and class_section_id separately,
 * this FK points to the TeacherClassSectionSubject model which already encodes
 * all three relationships plus the assignment role. Using this FK:
 *   1. Guarantees only pre-approved teacher-subject assignments get slotted.
 *      You cannot slot a teacher for a subject they're not assigned to teach.
 *   2. Captures the role context (subject_teacher vs co_teacher) automatically.
 *   3. Simplifies the timetable grid query — one join resolves teacher + subject.
 *
 * ── `class_section_id` Denormalization ───────────────────────────────────────
 * class_section_id is derivable via teacher_class_section_subject.class_section_id.
 * It is stored redundantly because:
 *   (a) It enables the DB-level unique constraint preventing double-booking.
 *   (b) The student/teacher view always filters by class_section_id —
 *       avoiding a join on this hot query path.
 * The application (StoreTimetableSlotRequest) validates that these two match.
 *
 * ── `is_manually_placed` Flag ────────────────────────────────────────────────
 * Slots placed or moved by admin (not the generator) are flagged with
 * is_manually_placed = true. When a re-generation is triggered, only slots
 * with is_manually_placed = false are cleared. This preserves admin overrides
 * across multiple generation runs — critical for iterative schedule building.
 *
 * ── Conflict Checking ────────────────────────────────────────────────────────
 * Two conflict types exist:
 *
 * Section double-booking (DB-enforced):
 *   UNIQUE (timetable_id, class_section_id, class_period_id, day_of_week)
 *
 * Teacher double-booking (application-enforced via NoTeacherConflict rule):
 *   Cannot place a teacher in two different sections at the same period + day.
 *   Checked in StoreTimetableSlotRequest and TimetableService::addSlot().
 *
 * ── Properties ───────────────────────────────────────────────────────────────
 * @property string      $id                       UUID
 * @property string      $school_id
 * @property string      $timetable_id
 * @property string      $class_section_id
 * @property int         $class_period_id
 * @property int         $teacher_class_section_subject_id
 * @property int         $day_of_week              1=Mon..7=Sun
 * @property bool        $is_manually_placed
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * ── Relationships ────────────────────────────────────────────────────────────
 * @property-read Timetable                     $timetable
 * @property-read ClassSection                  $classSection
 * @property-read ClassPeriod                   $period
 * @property-read TeacherClassSectionSubject    $assignment
 */
class TimetableSlot extends Model
{
    use HasFactory;
    use HasUuids;
    use BelongsToSchool;
    use HasTableQuery;
    use SoftDeletes;
    use LogsActivity;

    protected $table = 'timetable_slots';

    protected $fillable = [
        'school_id',
        'timetable_id',
        'class_section_id',
        'class_period_id',
        'teacher_class_section_subject_id',
        'day_of_week',
        'is_manually_placed',
        'notes',
    ];

    protected $casts = [
        'class_period_id' => 'integer',
        'teacher_class_section_subject_id' => 'integer',
        'day_of_week' => 'integer',
        'is_manually_placed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ── HasTableQuery Configuration ───────────────────────────────────────────

    protected array $hiddenTableColumns = [
        'school_id',
        'deleted_at',
    ];

    protected array $defaultHiddenColumns = [
        'is_manually_placed',
        'notes',
        'created_at',
        'updated_at',
    ];

    protected array $globalFilterFields = [
        'day_of_week',
        'notes',
    ];

    // ── Activity Logging ──────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('timetable_slot')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn(string $eventName) =>
                "Timetable slot for \"{$this->classSection?->display_name}\" " .
                "on day {$this->day_of_week}, period {$this->period?->name} was {$eventName}"
            );
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * The parent timetable this slot belongs to.
     */
    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class, 'timetable_id');
    }

    /**
     * The class section arm this slot is assigned to.
     * Denormalized from the assignment but stored for query performance.
     */
    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    /**
     * The period of the school day this slot occupies.
     * Used to derive clock start/end times via the parent PeriodSchedule.
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(ClassPeriod::class, 'class_period_id');
    }

    /**
     * The teacher-subject assignment that fills this slot.
     * Encodes teacher_id + subject_id + class_section_id + role in one FK.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TeacherClassSectionSubject::class, 'teacher_class_section_subject_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Filter slots for a specific timetable.
     */
    public function scopeForTimetable(Builder $query, string $timetableId): Builder
    {
        return $query->where('timetable_id', $timetableId);
    }

    /**
     * Filter slots for a specific class section arm.
     * Used to render one arm's personal timetable view.
     *
     * Example: TimetableSlot::forSection($section->id)->forTimetable($id)->get()
     */
    public function scopeForSection(Builder $query, string $classSectionId): Builder
    {
        return $query->where('class_section_id', $classSectionId);
    }

    /**
     * Filter slots for a specific day of the week.
     *
     * Example: $timetable->slots()->onDay(3)->get() // Wednesday
     */
    public function scopeOnDay(Builder $query, int $dayOfWeek): Builder
    {
        return $query->where('day_of_week', $dayOfWeek);
    }

    /**
     * Only manually-placed slots (protected from re-generation).
     */
    public function scopeManuallyPlaced(Builder $query): Builder
    {
        return $query->where('is_manually_placed', true);
    }

    /**
     * Only auto-generated slots (cleared on re-generation).
     */
    public function scopeAutoGenerated(Builder $query): Builder
    {
        return $query->where('is_manually_placed', false);
    }

    /**
     * Filter slots for a specific teacher (via the assignment relationship).
     * Used to build a teacher's personal schedule view.
     *
     * Example: TimetableSlot::forTeacher($staffId)->with('period', 'classSection')->get()
     */
    public function scopeForTeacher(Builder $query, string $teacherId): Builder
    {
        return $query->whereHas(
            'assignment',
            fn(Builder $q) => $q->where('teacher_id', $teacherId)
        );
    }

    /**
     * Order by day of week then by period order.
     * Use when rendering the full week view in sequence.
     */
    public function scopeInWeekOrder(Builder $query): Builder
    {
        return $query
            ->orderBy('day_of_week')
            ->orderBy(
                ClassPeriod::select('order')
                    ->whereColumn('class_periods.id', 'timetable_slots.class_period_id')
                    ->limit(1)
            );
    }

    // ── Computed Accessors ────────────────────────────────────────────────────

    /**
     * Human-readable day name.
     *
     * @return string e.g. "Monday", "Wednesday"
     */
    public function getDayNameAttribute(): string
    {
        return match ($this->day_of_week) {
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
            default => "Day {$this->day_of_week}",
        };
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    /**
     * Mark this slot as manually placed by an admin.
     * Called when admin drags or manually assigns a slot in the builder.
     * Once marked, this slot will be preserved across re-generation runs.
     */
    public function markAsManuallyPlaced(): bool
    {
        return $this->update(['is_manually_placed' => true]);
    }

    /**
     * Reset this slot's manual flag, making it eligible for overwriting
     * on the next generation run.
     * Admin can use this to "release" a manual override and let the
     * generator take over the slot again.
     */
    public function releaseManualFlag(): bool
    {
        return $this->update(['is_manually_placed' => false]);
    }

    /**
     * Check whether adding this slot to the given timetable would create a
     * teacher double-booking conflict (same teacher, same period, same day).
     *
     * This is a static helper used by the NoTeacherConflict validation rule
     * and TimetableService::addSlot(). It does not check section conflicts —
     * those are enforced by the DB unique constraint.
     *
     * @param string $timetableId
     * @param int    $teacherClassSectionSubjectId  The assignment being placed
     * @param int    $classPeriodId
     * @param int    $dayOfWeek
     * @param string|null $excludeSlotId            Exclude this slot ID (for update checks)
     * @return bool  true = conflict exists, false = no conflict
     */
    public static function teacherConflictExists(
        string $timetableId,
        int $teacherClassSectionSubjectId,
        int $classPeriodId,
        int $dayOfWeek,
        ?string $excludeSlotId = null
    ): bool {
        // Resolve the teacher_id from the assignment, then check all slots
        // for that teacher at the same period/day in this timetable.
        $teacherId = TeacherClassSectionSubject::where('id', $teacherClassSectionSubjectId)
            ->value('teacher_id');

        if (!$teacherId) {
            return false; // Assignment not found — let FK constraint handle it
        }

        return self::where('timetable_id', $timetableId)
            ->where('class_period_id', $classPeriodId)
            ->where('day_of_week', $dayOfWeek)
            ->whereHas(
                'assignment',
                fn(Builder $q) => $q->where('teacher_id', $teacherId)
            )
            ->when($excludeSlotId, fn($q) => $q->whereKeyNot($excludeSlotId))
            ->whereNull('deleted_at')
            ->exists();
    }
}
