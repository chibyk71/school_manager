<?php

/**
 * Migration: create_timetable_day_schedules_table
 *
 * Creates the `timetable_day_schedules` table — the mapping that answers:
 * "For this timetable, which period schedule runs on Monday? Which on Friday?"
 *
 * ── What This Table Stores ───────────────────────────────────────────────────
 * Each row says: "For timetable X, day Y uses period schedule Z."
 *
 * Example rows for an SSS Term 1 timetable:
 *   timetable_id | day_of_week | period_schedule_id
 *   -------------|-------------|-------------------
 *   uuid-sss-t1  |      1      | id-regular-day        (Monday)
 *   uuid-sss-t1  |      2      | id-regular-day        (Tuesday)
 *   uuid-sss-t1  |      3      | id-regular-day        (Wednesday)
 *   uuid-sss-t1  |      4      | id-regular-day        (Thursday)
 *   uuid-sss-t1  |      5      | id-short-friday       (Friday — different schedule)
 *
 * And a boarding school that includes Saturday morning extra lessons:
 *   uuid-sss-t1  |      6      | id-extra-lessons      (Saturday)
 *
 * ── Why This Is Per-Timetable (Not Per-School) ────────────────────────────────
 * Initially this could seem like a school-level setting: "Friday is always Short
 * Friday." But timetables can legitimately differ:
 *   - Term 1 timetable: Friday = Short Friday (assembly day)
 *   - Exam week timetable: every day = Exam Day schedule
 *   - Extra lessons timetable: Saturday = Extra Lessons schedule
 *
 * Keeping it per-timetable gives full flexibility. The TimetableController
 * provides a "copy from section defaults" action to reduce repetitive setup.
 *
 * ── Day of Week Convention ────────────────────────────────────────────────────
 * Integer encoding following ISO 8601:
 *   1 = Monday, 2 = Tuesday, 3 = Wednesday, 4 = Thursday,
 *   5 = Friday, 6 = Saturday, 7 = Sunday
 *
 * This makes ORDER BY day_of_week produce Mon→Sun ordering naturally.
 * Days not present in this table are considered non-working days for the timetable.
 * The generator uses only days that have a day_schedule row.
 *
 * ── Relationship to Other Tables ────────────────────────────────────────────
 *   timetable_day_schedules  >─ timetables       (belongs to one timetable)
 *   timetable_day_schedules  >─ period_schedules  (uses one schedule per day)
 *
 * Indirect:
 *   timetable  →  timetable_day_schedules  →  period_schedules  →  class_periods
 *   This chain resolves: "what periods exist on Tuesday for this timetable?"
 *
 * ── Key Design Decisions ─────────────────────────────────────────────────────
 * - No soft deletes — these are pure configuration rows, not data rows with
 *   referential history. Removing a day mapping means "this timetable no longer
 *   operates on that day," which is immediate and reversible.
 *
 * - No `school_id` column — derivable via timetable FK. Unlike class_periods,
 *   this table is never queried directly by school_id; it is always loaded
 *   through a timetable instance. The join cost is negligible.
 *
 * - Unique constraint on (timetable_id, day_of_week) — one schedule per day
 *   per timetable. The UI enforces this by using a dropdown (one selection per day).
 *
 * ── Belongs To ───────────────────────────────────────────────────────────────
 * Timetable Module → Core configuration layer. Managed as part of the timetable
 * creation/edit form (not a separate CRUD resource). When admin creates or edits
 * a timetable, they configure the day→schedule mapping in the same form.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_day_schedules', function (Blueprint $table) {
            // ── Primary Key ───────────────────────────────────────────────────
            $table->id();
            // Simple auto-increment — these are config rows loaded via
            // timetable relationship, not directly by their own ID.

            // ── Parent Timetable ──────────────────────────────────────────────
            $table->foreignUuid('timetable_id')
                ->constrained('timetables')
                ->cascadeOnDelete();
            // Deleting a timetable removes all its day-schedule mappings.
            // Cascade is correct — mappings have no independent existence.

            // ── Period Schedule ───────────────────────────────────────────────
            $table->foreignId('period_schedule_id')
                ->constrained('period_schedules')
                ->restrictOnDelete();
            // Restrict — cannot delete a period schedule that is mapped to
            // an active or historical timetable day. Admin must remap first.

            // ── Day of Week ───────────────────────────────────────────────────
            $table->unsignedTinyInteger('day_of_week');
            // ISO 8601 integer: 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday,
            //                   5=Friday, 6=Saturday, 7=Sunday
            // Only days listed here are considered working days for the timetable.
            // The generator iterates only over days that have a row in this table.

            // ── Timestamps ───────────────────────────────────────────────────
            $table->timestamps();
            // No soft deletes — these are config rows, not data rows.
            // Deletion is immediate and reversible (just re-add the mapping).

            // ── Constraints ───────────────────────────────────────────────────
            $table->unique(
                ['timetable_id', 'day_of_week'],
                'uq_timetable_day_schedules_timetable_day'
            );
            // One schedule per day per timetable. Enforced at DB level.
            // The UI renders a dropdown for each day (Mon–Sat) — selecting one
            // schedule per day makes duplicates impossible through the UI,
            // but this DB constraint protects against direct API calls.

            // ── Indexes ───────────────────────────────────────────────────────
            $table->index(
                ['timetable_id', 'day_of_week'],
                'idx_timetable_day_schedules_lookup'
            );
            // Used when the generator asks: "what period schedule runs on
            // Wednesday for timetable X?" Covered by the unique constraint
            // but explicit index improves query plan readability.

            $table->index('period_schedule_id', 'idx_timetable_day_schedules_schedule');
            // Used for the restrictOnDelete check and for querying
            // "which timetables use this schedule?" (needed before deletion).
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_day_schedules');
    }
};
