<?php

namespace App\Models\Academic;

use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ClassPeriod — A single time slot within a PeriodSchedule.
 *
 * Each ClassPeriod represents one block of time in a school day: either a
 * lesson period (assignable to a teacher/subject) or a break (shaded row in
 * the grid, not assignable). Periods are ordered within their parent schedule
 * and their clock start times are computed from the schedule's school_start_time
 * + cumulative preceding durations.
 *
 * ── Real-World Examples ───────────────────────────────────────────────────────
 * For a "Regular Day" schedule starting at 08:00:
 *
 *   order | name           | duration | is_break | computed_start
 *   ------|----------------|----------|----------|-----------------
 *     1   | Period 1       |    40    |  false   |  08:00
 *     2   | Period 2       |    40    |  false   |  08:40
 *     3   | Period 3       |    40    |  false   |  09:20
 *     4   | Short Break    |    20    |  true    |  10:00
 *     5   | Period 4       |    40    |  false   |  10:20
 *     6   | Period 5       |    40    |  false   |  11:00
 *     7   | Long Break     |    30    |  true    |  11:40
 *     8   | Period 6       |    40    |  false   |  12:10
 *     9   | Period 7       |    40    |  false   |  12:50
 *    10   | Period 8       |    40    |  false   |  13:30
 *
 * For "Short Friday" — same structure but Period 8 has duration_minutes = 30.
 *
 * ── How It Fits in the Module ─────────────────────────────────────────────────
 * ClassPeriod is the atomic unit of scheduling. TimetableSlot rows reference
 * a class_period_id to specify WHEN a lesson occurs. The generator iterates
 * lesson periods (is_break = false) and tries to assign one teacher-subject
 * pair to each one.
 *
 * Admin never interacts with ClassPeriod directly from the timetable builder —
 * periods are configured in the Period Schedules settings area. The builder
 * just renders them as rows in the weekly grid.
 *
 * ── Clock Time Is NOT Stored ─────────────────────────────────────────────────
 * Start and end times for each period are computed, not stored:
 *   computed_start = schedule.school_start_time + sum(durations of all periods with order < this one)
 *
 * Use PeriodSchedule::computedPeriodTimes() to get a pre-built lookup array
 * covering all periods in the schedule at once.
 *
 * ── Properties ───────────────────────────────────────────────────────────────
 * @property int         $id
 * @property string      $school_id
 * @property int         $period_schedule_id
 * @property string      $name
 * @property int         $order
 * @property int         $duration_minutes
 * @property bool        $is_break
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * ── Relationships ────────────────────────────────────────────────────────────
 * @property-read PeriodSchedule                                            $schedule
 * @property-read \Illuminate\Database\Eloquent\Collection<TimetableSlot>  $slots
 */
class ClassPeriod extends Model
{
    use HasFactory;
    use BelongsToSchool;
    use HasTableQuery;
    use SoftDeletes;
    use LogsActivity;

    protected $table = 'class_periods';

    protected $fillable = [
        'school_id',
        'period_schedule_id',
        'name',
        'order',
        'duration_minutes',
        'is_break',
    ];

    protected $casts = [
        'order' => 'integer',
        'duration_minutes' => 'integer',
        'is_break' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ── HasTableQuery Configuration ───────────────────────────────────────────

    /**
     * Columns never exposed to the DataTable frontend.
     */
    protected array $hiddenTableColumns = [
        'school_id',
        'deleted_at',
    ];

    /**
     * Columns initially hidden but toggleable by the user.
     */
    protected array $defaultHiddenColumns = [
        'created_at',
        'updated_at',
    ];

    /**
     * Fields searched via the DataTable global search.
     */
    protected array $globalFilterFields = [
        'name',
    ];

    // ── Activity Logging ──────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('class_period')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn(string $eventName) =>
                "Period \"{$this->name}\" (order {$this->order}) in schedule " .
                "\"{$this->schedule?->name}\" was {$eventName}"
            );
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * The parent schedule this period belongs to.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PeriodSchedule::class, 'period_schedule_id');
    }

    /**
     * All timetable slots that reference this period.
     * Used to check whether a period is in use before allowing deletion.
     * Also used for substitution/cover queries: "who is teaching during Period 3?"
     */
    public function slots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class, 'class_period_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Only lesson periods (is_break = false).
     * Used by the generator to list assignable periods.
     *
     * Example: $schedule->periods()->lessons()->get()
     */
    public function scopeLessons(Builder $query): Builder
    {
        return $query->where('is_break', false);
    }

    /**
     * Only break/lunch periods (is_break = true).
     * Used when rendering the grid to identify shaded, non-assignable rows.
     */
    public function scopeBreaks(Builder $query): Builder
    {
        return $query->where('is_break', true);
    }

    /**
     * Order by position within the schedule.
     * Always chain this when fetching periods for display or time computation.
     *
     * Example: ClassPeriod::forSchedule($id)->ordered()->get()
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }

    /**
     * Filter periods belonging to a specific schedule.
     *
     * Example: ClassPeriod::forSchedule($scheduleId)->lessons()->ordered()->get()
     */
    public function scopeForSchedule(Builder $query, int $scheduleId): Builder
    {
        return $query->where('period_schedule_id', $scheduleId);
    }

    // ── Computed Accessors ────────────────────────────────────────────────────

    /**
     * Human-readable label for UI display.
     * Returns the name as-is; callers can append clock times if available.
     *
     * Example outputs: "Period 1", "Morning Break", "Long Break", "Period 8"
     */
    public function getDisplayLabelAttribute(): string
    {
        return $this->name;
    }

    /**
     * Whether this period can have a lesson assigned to it.
     * Breaks cannot be assigned; lessons can.
     */
    public function isAssignable(): bool
    {
        return !$this->is_break;
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    /**
     * Whether this period is referenced by any active timetable slot.
     * Used to block deletion of periods that are currently in use.
     *
     * Checks non-soft-deleted slots only — a period referenced only by deleted
     * slots can be safely soft-deleted.
     */
    public function isInUse(): bool
    {
        return $this->slots()->whereNull('deleted_at')->exists();
    }

    /**
     * The next period in the same schedule (by order), or null if this is last.
     * Used when computing whether two consecutive periods can form a double period.
     */
    public function nextPeriod(): ?self
    {
        return self::where('period_schedule_id', $this->period_schedule_id)
            ->where('order', '>', $this->order)
            ->orderBy('order')
            ->first();
    }

    /**
     * The previous period in the same schedule, or null if this is first.
     */
    public function previousPeriod(): ?self
    {
        return self::where('period_schedule_id', $this->period_schedule_id)
            ->where('order', '<', $this->order)
            ->orderByDesc('order')
            ->first();
    }
}
