<?php

namespace App\Models\Academic;

use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * PeriodSchedule — Named day schedule template.
 *
 * A PeriodSchedule defines the structure of a school day: how many periods there
 * are, how long each lasts, where the breaks fall, and what time the day starts.
 * It is a reusable template — one schedule can be shared across many timetable days.
 *
 * ── Real-World Examples ───────────────────────────────────────────────────────
 *   "Regular Day"    — standard Monday–Thursday: 8×40-min lessons, 2 breaks
 *   "Short Friday"   — same structure but last period is 30 min (Jumat/assembly)
 *   "Exam Day"       — 2×2-hour exam slots, extended supervised break
 *   "Extra Lessons"  — after-school: 3×45-min slots, minimal break
 *
 * ── How It Fits in the Module ─────────────────────────────────────────────────
 * PeriodSchedule sits at the settings layer of the timetable module.
 * Admin configures schedules once (via PeriodScheduleController) before creating
 * any timetables. A Timetable then maps each working day to a PeriodSchedule
 * via the TimetableDaySchedule pivot.
 *
 *   PeriodSchedule
 *     ──< ClassPeriod            (the ordered slots within this schedule)
 *     ──< TimetableDaySchedule   (which timetables use this schedule on which day)
 *
 * ── Clock Time Computation ────────────────────────────────────────────────────
 * Computed start times are NOT stored on ClassPeriod rows. Instead:
 *   Period 1 starts at: $schedule->school_start_time
 *   Period N starts at: school_start_time + sum(durations of periods 1..N-1)
 *
 * This means changing one break duration automatically shifts all subsequent
 * period start times without touching any ClassPeriod rows. The computation
 * is done in the `computedPeriodTimes()` method below.
 *
 * ── Properties ───────────────────────────────────────────────────────────────
 * @property int              $id
 * @property string           $school_id
 * @property string           $name
 * @property string|null      $description
 * @property string|null      $school_start_time   e.g. "08:00:00"
 * @property bool             $is_active
 * @property string|null      $color               e.g. "#3B82F6"
 * @property int              $sort_order
 * @property Carbon|null      $created_at
 * @property Carbon|null      $updated_at
 * @property Carbon|null      $deleted_at
 *
 * ── Relationships ────────────────────────────────────────────────────────────
 * @property-read \Illuminate\Database\Eloquent\Collection<ClassPeriod>           $periods
 * @property-read \Illuminate\Database\Eloquent\Collection<ClassPeriod>           $lessonPeriods
 * @property-read \Illuminate\Database\Eloquent\Collection<ClassPeriod>           $breakPeriods
 * @property-read \Illuminate\Database\Eloquent\Collection<TimetableDaySchedule>  $timetableDays
 */
class PeriodSchedule extends Model
{
    use HasFactory;
    use BelongsToSchool;
    use HasTableQuery;
    use SoftDeletes;
    use LogsActivity;

    protected $table = 'period_schedules';

    protected $fillable = [
        'school_id',
        'name',
        'description',
        'school_start_time',
        'is_active',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
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
        'description',
        'color',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    /**
     * Fields searched when the admin uses the DataTable global search box.
     */
    protected array $globalFilterFields = [
        'name',
        'description',
    ];

    // ── Activity Logging ──────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('period_schedule')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn(string $eventName) => "Period schedule \"{$this->name}\" was {$eventName}"
            );
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * All periods (lessons + breaks) in this schedule, ordered by position.
     * This is the full day view — used for grid rendering and clock-time computation.
     */
    public function periods(): HasMany
    {
        return $this->hasMany(ClassPeriod::class, 'period_schedule_id')
            ->orderBy('order');
    }

    /**
     * Only the lesson periods (breaks excluded), ordered by position.
     * Used by the timetable generator to enumerate assignable slots.
     */
    public function lessonPeriods(): HasMany
    {
        return $this->hasMany(ClassPeriod::class, 'period_schedule_id')
            ->where('is_break', false)
            ->orderBy('order');
    }

    /**
     * Only the break/lunch periods, ordered by position.
     * Used when rendering the timetable grid (breaks are shown as shaded rows
     * and cannot be used as drop targets in the builder).
     */
    public function breakPeriods(): HasMany
    {
        return $this->hasMany(ClassPeriod::class, 'period_schedule_id')
            ->where('is_break', true)
            ->orderBy('order');
    }

    /**
     * All timetable-day mappings that reference this schedule.
     * Used to check whether a schedule is "in use" before allowing deletion.
     */
    public function timetableDays(): HasMany
    {
        return $this->hasMany(TimetableDaySchedule::class, 'period_schedule_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Only active schedules.
     * Used in the timetable builder day-mapping dropdown.
     *
     * Example: PeriodSchedule::active()->forSchool($schoolId)->ordered()->get()
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Order by the admin-defined sort_order, then by name alphabetically.
     * Ensures consistent display ordering in dropdowns and settings tables.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ── Computed Time Logic ───────────────────────────────────────────────────

    /**
     * Compute the start and end clock time for every period in this schedule.
     *
     * Returns an array keyed by class_period.id:
     * [
     *   1 => ['start' => '08:00', 'end' => '08:40'],
     *   2 => ['start' => '08:40', 'end' => '09:20'],
     *   3 => ['start' => '09:20', 'end' => '09:30'],  // break
     *   ...
     * ]
     *
     * When school_start_time is null, returns an empty array. The grid then
     * renders ordinal labels ("Period 1", "Period 2") instead of clock times.
     *
     * IMPORTANT: Call $schedule->load('periods') before this method to avoid
     * an N+1 query. This method iterates $this->periods — if not loaded, each
     * iteration triggers a separate DB query.
     *
     * @return array<int, array{start: string, end: string}>
     */
    public function computedPeriodTimes(): array
    {
        if (!$this->school_start_time) {
            return [];
        }

        // Parse anchor time into total minutes from midnight.
        [$startHour, $startMinute] = array_map('intval', explode(':', $this->school_start_time));
        $cursorMinutes = ($startHour * 60) + $startMinute;

        $result = [];

        foreach ($this->periods as $period) {
            $startH = intdiv($cursorMinutes, 60);
            $startM = $cursorMinutes % 60;

            $endMinutes = $cursorMinutes + $period->duration_minutes;
            $endH = intdiv($endMinutes, 60);
            $endM = $endMinutes % 60;

            $result[$period->id] = [
                'start' => sprintf('%02d:%02d', $startH, $startM),
                'end' => sprintf('%02d:%02d', $endH, $endM),
            ];

            $cursorMinutes = $endMinutes;
        }

        return $result;
    }

    /**
     * Total duration of the school day in minutes (lessons + breaks combined).
     *
     * Example: 8×40-min lessons + 20-min short break + 30-min long break = 370 min
     *
     * Requires $this->periods to be loaded.
     */
    public function totalDurationMinutes(): int
    {
        return $this->periods->sum('duration_minutes');
    }

    /**
     * Count of lesson periods only (breaks excluded).
     * Used by the generator to know how many assignable slots exist per day
     * when this schedule is active.
     *
     * Requires $this->periods to be loaded.
     */
    public function lessonPeriodCount(): int
    {
        return $this->periods->where('is_break', false)->count();
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    /**
     * Whether this schedule is referenced by any timetable day mapping.
     * Used to block deletion of in-use schedules.
     *
     * Does not check soft-deleted timetables — a schedule referenced only by
     * deleted timetables can still be considered safe to delete.
     */
    public function isInUse(): bool
    {
        return $this->timetableDays()
            ->whereHas('timetable', fn($q) => $q->whereNull('deleted_at'))
            ->exists();
    }

    /**
     * Make this schedule available in the day-mapping dropdown.
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Remove this schedule from the day-mapping dropdown without deleting it.
     * Existing timetables that reference this schedule are unaffected.
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }
}
