<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * TimetableDaySchedule — Maps a specific day of the week to a PeriodSchedule for a timetable.
 *
 * This is the bridge that answers: "On Monday, which bell schedule (period structure)
 * does this timetable use?" It allows different days to have different schedules
 * (e.g. Regular Day Mon–Thu, Short Friday on Friday, Extra Lessons on Saturday).
 *
 * ── Real-World Example ───────────────────────────────────────────────────────
 * For an SSS Term 1 timetable:
 *
 *   timetable_id       | day_of_week | period_schedule_id
 *   -------------------|-------------|--------------------
 *   uuid-sss-t1        |      1      | Regular Day
 *   uuid-sss-t1        |      2      | Regular Day
 *   uuid-sss-t1        |      3      | Regular Day
 *   uuid-sss-t1        |      4      | Regular Day
 *   uuid-sss-t1        |      5      | Short Friday
 *   uuid-sss-t1        |      6      | Extra Lessons (Saturday)
 *
 * ── Properties ───────────────────────────────────────────────────────────────
 * @property int         $id
 * @property string      $timetable_id
 * @property int         $period_schedule_id
 * @property int         $day_of_week          1=Mon … 7=Sun (ISO 8601)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * ── Relationships ────────────────────────────────────────────────────────────
 * @property-read Timetable       $timetable
 * @property-read PeriodSchedule  $schedule
 */
class TimetableDaySchedule extends Model
{
    use HasFactory;

    protected $table = 'timetable_day_schedules';

    protected $fillable = [
        'timetable_id',
        'period_schedule_id',
        'day_of_week',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * The timetable this day mapping belongs to.
     */
    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class, 'timetable_id');
    }

    /**
     * The period schedule (bell times + periods) used on this day.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PeriodSchedule::class, 'period_schedule_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Order by day_of_week (Monday → Sunday).
     */
    public function scopeInWeekOrder(Builder $query): Builder
    {
        return $query->orderBy('day_of_week');
    }

    /**
     * Only mappings for a specific day.
     *
     * Example: $timetable->daySchedules()->forDay(5)->first() // Friday
     */
    public function scopeForDay(Builder $query, int $dayOfWeek): Builder
    {
        return $query->where('day_of_week', $dayOfWeek);
    }
}
