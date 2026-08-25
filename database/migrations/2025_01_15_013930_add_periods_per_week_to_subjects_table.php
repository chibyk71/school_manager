<?php

/**
 * Migration: add_periods_per_week_to_subjects_table
 *
 * Adds the `periods_per_week` column to the existing `subjects` table.
 * This column tells the timetable generator how many times per week a given
 * subject should appear in each class section's timetable.
 *
 * ── Why This Column Lives on `subjects` ──────────────────────────────────────
 * `periods_per_week` is a subject-level default that applies globally across
 * all schools and sections unless overridden. For example:
 *   - Mathematics: 5 periods/week (core subject, heavy emphasis)
 *   - Fine Art: 2 periods/week (elective, lighter load)
 *   - Physical Education: 3 periods/week
 *   - Biology: 4 periods/week
 *
 * Storing it on the subject avoids repetitive configuration — an admin doesn't
 * need to specify "Mathematics = 5 times/week" for every class section. They
 * set it once on the subject.
 *
 * ── Future: Per-Section Override ─────────────────────────────────────────────
 * A `school_section_subjects` table can later provide per-section overrides:
 *   - JSS: Maths = 6/week, SSS: Maths = 5/week
 * This migration handles the global default. The generator will check for
 * section-level overrides first, falling back to this column.
 *
 * ── Nullable Design ───────────────────────────────────────────────────────────
 * Null means "not configured." The generator treats null as "use school default"
 * and looks up a school-settings value. If both are null, the generator logs a
 * warning and records a 'frequency_unmet' conflict rather than silently skipping
 * or crashing.
 *
 * We do NOT use a default of 0 (ambiguous: zero vs unconfigured).
 * We do NOT use a default of 1 (would produce wrong schedules silently for most subjects).
 *
 * ── Belongs To ───────────────────────────────────────────────────────────────
 * Timetable Module → Subject Module interface.
 * Written here (not in the subjects migration) to keep the timetable module's
 * schema additions self-contained and independently reversible.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedTinyInteger('periods_per_week')
                ->nullable()
                ->after('school_id');
            // Nullable unsigned tinyint:
            //   - Unsigned: a subject cannot appear a negative number of times
            //   - Tinyint: max value 255, sufficient (no subject needs 256 periods/week)
            //   - Nullable: null = "not configured, fall back to school default"
            //
            // Practical range for Nigerian secondary schools:
            //   Core subjects (Maths, English):  4–6 periods/week
            //   Science subjects:                3–5 periods/week
            //   Electives / Arts:                2–3 periods/week
            //   PE / Practical:                  1–3 periods/week
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('periods_per_week');
        });
    }
};
