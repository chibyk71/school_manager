<?php

/**
 * Migration: create_class_periods_table
 *
 * Creates the `class_periods` table — the individual time slots (periods and breaks)
 * that make up a school day schedule. Each row is one slot within a PeriodSchedule.
 *
 * ── What This Table Stores ───────────────────────────────────────────────────
 * A ClassPeriod is a single block of time within a named schedule. Examples
 * within a "Regular Day" schedule for a Nigerian secondary school:
 *
 *   order | name          | duration_minutes | is_break
 *   ------|---------------|------------------|----------
 *     1   | Period 1      | 40               | false
 *     2   | Period 2      | 40               | false
 *     3   | Period 3      | 40               | false
 *     4   | Short Break   | 20               | true
 *     5   | Period 4      | 40               | false
 *     6   | Period 5      | 40               | false
 *     7   | Long Break    | 30               | true
 *     8   | Period 6      | 40               | false
 *     9   | Period 7      | 40               | false
 *    10   | Period 8      | 40               | false
 *
 * For a "Short Friday" schedule, Period 8 would have duration_minutes = 30.
 *
 * ── Computed Clock Times ──────────────────────────────────────────────────────
 * Period start times are NOT stored. They are computed at runtime:
 *   Period 1 start = schedule.school_start_time
 *   Period N start = schedule.school_start_time + sum(durations[order 1..N-1])
 *
 * This means changing a break duration automatically shifts all subsequent
 * period start times without requiring data updates.
 *
 * ── Relationship to Other Tables ────────────────────────────────────────────
 *   period_schedules   ──< class_periods          (schedule has ordered periods)
 *   class_periods      ──< timetable_slots        (slots reference a specific period)
 *   class_periods      ──< timetable_conflicts    (conflicts reference an unresolved period)
 *   class_periods      ──< timetable_day_schedules (via the schedule FK)
 *
 * ── Key Design Decisions ─────────────────────────────────────────────────────
 * - `school_id` is denormalized here (derivable via period_schedule) for query
 *   performance. Timetable slot lookups always filter by school_id first.
 *
 * - `duration_minutes` is unsigned smallint — supports up to 65535 min periods
 *   which is more than sufficient. Integer avoids float imprecision.
 *
 * - No `is_extra` flag — extra/after-school lessons are handled by creating a
 *   separate PeriodSchedule named "Extra Lessons" and assigning it to the
 *   relevant days in `timetable_day_schedules`. Keeping this model clean.
 *
 * - SoftDeletes are included because timetable slots reference class_period_id.
 *   Deleting a period that has slots attached would orphan data. Soft delete
 *   preserves the reference while preventing reuse.
 *
 * - The unique constraint on (period_schedule_id, order) ensures no two periods
 *   in the same schedule share the same position, which would make computing
 *   start times ambiguous.
 *
 * ── Belongs To ───────────────────────────────────────────────────────────────
 * Timetable Module → Settings sub-layer. Managed alongside PeriodSchedule via
 * PeriodScheduleController (periods are nested resources of schedules).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_periods', function (Blueprint $table) {
            // ── Primary Key ───────────────────────────────────────────────────
            $table->id(); // bigIncrements — config rows, not UUIDs

            // ── Multi-Tenant Anchor (denormalized) ────────────────────────────
            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->cascadeOnDelete();
            // Denormalized for query performance. Derivable via period_schedule
            // but denormalizing avoids a join on every timetable slot lookup.

            // ── Parent Schedule ───────────────────────────────────────────────
            $table->foreignId('period_schedule_id')
                ->constrained('period_schedules')
                ->cascadeOnDelete();
            // Deleting a schedule removes all its periods. This is intentional —
            // a schedule without periods is meaningless, and the schedule-level
            // soft delete on period_schedules prevents accidental removal of
            // schedules that have been used in timetables.

            // ── Identity ─────────────────────────────────────────────────────
            $table->string('name', 100);
            // Human-readable name. Examples: "Period 1", "Morning Break",
            // "Period 5", "Long Break", "Period 8".
            // The frontend auto-suggests "Period N" or "Break" based on is_break,
            // but the stored value is admin-controlled.

            // ── Ordering ──────────────────────────────────────────────────────
            $table->unsignedTinyInteger('order');
            // Position within the schedule. Must be unique per schedule (see constraint).
            // Used to compute clock start times: start = schedule.school_start_time
            // + sum of durations for all periods with order < this one.
            // Range 0-255 is sufficient (no school has 256 periods per day).

            // ── Duration ─────────────────────────────────────────────────────
            $table->unsignedSmallInteger('duration_minutes');
            // Length of this period/break in minutes. No default — must be explicit.
            // Examples: 40 (standard lesson), 20 (short break), 30 (long break/lunch).
            // The last period of a short day might differ: Regular Day=40, Short Friday=30.
            // This is why "Short Friday" needs its own PeriodSchedule rather than
            // overriding individual period durations.

            // ── Type Flags ───────────────────────────────────────────────────
            $table->boolean('is_break')->default(false);
            // When true: this slot is a break/lunch and cannot have a lesson assigned.
            // The timetable builder renders break rows differently (shaded, no drop target).
            // The generator skips break periods entirely.

            // ── Timestamps & Soft Deletes ─────────────────────────────────────
            $table->timestamps();
            $table->softDeletes();
            // Soft delete protects periods referenced by timetable_slots.
            // A period used in an active timetable slot must not be hard-deleted.

            // ── Indexes ───────────────────────────────────────────────────────
            $table->unique(
                ['period_schedule_id', 'order'],
                'uq_class_periods_schedule_order'
            );
            // Ensures no two periods in the same schedule have the same position.
            // Required for unambiguous start-time computation.

            $table->unique(
                ['period_schedule_id', 'name'],
                'uq_class_periods_schedule_name'
            );
            // Prevents duplicate period names within a schedule (UX clarity).

            $table->index(
                ['school_id', 'period_schedule_id'],
                'idx_class_periods_school_schedule'
            );
            // Used when loading all periods for a school's timetable builder.

            $table->index(
                ['period_schedule_id', 'is_break'],
                'idx_class_periods_schedule_lessons'
            );
            // Used by the generator to quickly fetch only lesson periods
            // (skipping breaks) when computing available slots.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_periods');
    }
};
