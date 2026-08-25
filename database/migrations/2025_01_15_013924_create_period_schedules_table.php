<?php

/**
 * Migration: create_period_schedules_table
 *
 * Creates the `period_schedules` table — named day schedule templates that define
 * the structure of a school day (bell times, period ordering, break placement).
 *
 * ── What This Table Stores ───────────────────────────────────────────────────
 * A PeriodSchedule is a reusable school-day template. Examples:
 *   - "Regular Day"     — standard Mon–Thu schedule, 8 × 40-min periods + breaks
 *   - "Short Friday"    — same periods but last lesson is 30 min
 *   - "Exam Day"        — only 2 long exam slots + supervised break
 *   - "Extra Lessons"   — after-school extended schedule
 *
 * Schools define their schedules once here; individual timetables then map each
 * day of the week to a schedule via `timetable_day_schedules`. This separates
 * "what the school day looks like" from "which timetable uses which day shape."
 *
 * ── Relationship to Other Tables ────────────────────────────────────────────
 *   period_schedules  ──< class_periods              (a schedule has many periods)
 *   period_schedules  ──< timetable_day_schedules    (many timetables reference a schedule)
 *
 * ── Key Design Decisions ─────────────────────────────────────────────────────
 * - `school_start_time` is stored here (not on individual periods) because the
 *   anchor time belongs to the schedule, not a specific slot. All period start
 *   times are computed: Period N start = school_start_time + sum(durations[0..N-1]).
 *
 * - No `is_default` column — the concept of "default for this day" lives in the
 *   `timetable_day_schedules` mapping table, not here. A schedule is just a template.
 *
 * - `is_active` allows schools to retire old schedules without deleting them,
 *   preserving historical timetable integrity since slots reference periods which
 *   reference schedules.
 *
 * - `color` is a UI affordance — shown as a badge/chip when multiple schedules
 *   appear side by side in the timetable builder's day-tab strip.
 *
 * - SoftDeletes protects schedule data referenced by historical timetable slots.
 *   A deleted schedule should never orphan its periods or break timetable views.
 *
 * ── Belongs To ───────────────────────────────────────────────────────────────
 * Timetable Module → Settings sub-layer (admin configures schedules before
 * creating timetables). Managed via PeriodScheduleController.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_schedules', function (Blueprint $table) {
            // ── Primary Key ───────────────────────────────────────────────────
            $table->id(); // bigIncrements — schedules are school-config rows, not UUIDs

            // ── Multi-Tenant Anchor ───────────────────────────────────────────
            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->cascadeOnDelete(); // removing a school removes all its schedules

            // ── Identity ─────────────────────────────────────────────────────
            $table->string('name', 100);
            // e.g. "Regular Day", "Short Friday", "Exam Day", "Extra Lessons"

            $table->string('description', 255)->nullable();
            // Optional human-readable note: "Used Mon–Thu. 8 lessons, 2 breaks."

            // ── Schedule Anchor ───────────────────────────────────────────────
            $table->time('school_start_time')->nullable();
            // The clock time at which Period 1 (or the first break) begins.
            // Nullable because some schools may not care about clock times and
            // only use period ordering. When null, the grid renders ordinal labels
            // ("Period 1", "Period 2") instead of computed clock times.
            // Example: "08:00:00"

            // ── State ─────────────────────────────────────────────────────────
            $table->boolean('is_active')->default(true);
            // Inactive schedules are hidden from the day-mapping UI but preserved
            // so historical timetables that reference their periods still render.

            // ── UI Affordance ─────────────────────────────────────────────────
            $table->string('color', 7)->nullable();
            // Hex color code for visual distinction in the timetable builder UI.
            // Example: "#3B82F6" (blue for Regular Day), "#F59E0B" (amber for Exam Day)

            // ── Ordering ──────────────────────────────────────────────────────
            $table->unsignedSmallInteger('sort_order')->default(0);
            // Controls display order in schedule selector dropdowns.

            // ── Timestamps & Soft Deletes ──────────────────────────────────────
            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ────────────────────────────────────────────────────────
            $table->index(['school_id', 'is_active'], 'idx_period_schedules_school_active');
            // Used by the day-mapping UI to list active schedules for a school.

            $table->unique(['school_id', 'name'], 'uq_period_schedules_school_name');
            // Prevents duplicate schedule names within the same school.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_schedules');
    }
};
