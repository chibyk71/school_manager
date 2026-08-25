<?php

/**
 * Migration: create_timetable_conflicts_table
 *
 * Creates the `timetable_conflicts` table — a staging table for lesson slot
 * assignments that the auto-generator could not place without creating a conflict.
 *
 * ── What This Table Stores ───────────────────────────────────────────────────
 * When the auto-generator runs and cannot assign a subject-teacher pair to a
 * period without violating constraints, it writes a conflict record here instead
 * of silently skipping the assignment or forcing an invalid placement.
 *
 * Each conflict row describes:
 *   - What was being scheduled (class section + subject + teacher)
 *   - Why it could not be placed (conflict_type)
 *   - Possible alternative slots (suggested_alternatives JSON)
 *   - Whether an admin has resolved it (resolved_at)
 *
 * ── Conflict Types ────────────────────────────────────────────────────────────
 *   'teacher_double_booked'   — the teacher is already assigned to a different
 *                               class section at the required period/day. The
 *                               generator tried multiple periods/days but could
 *                               not find a conflict-free slot.
 *
 *   'section_double_booked'   — the class section already has a subject at every
 *                               available period for this day. Rare if period
 *                               schedules are configured correctly.
 *
 *   'no_available_period'     — ran out of available lesson periods on all working
 *                               days for this subject. Happens when periods_per_week
 *                               exceeds the total available lesson slots.
 *
 *   'no_teacher_assigned'     — a subject in the class section has no teacher
 *                               assignment in teacher_class_section_subjects.
 *                               Admin must assign a teacher before this can be resolved.
 *
 *   'frequency_unmet'         — the generator placed some but not all required
 *                               periods_per_week occurrences. The slot count is
 *                               in the description field.
 *
 * ── Resolution Workflow ───────────────────────────────────────────────────────
 * 1. Generator runs and creates conflict rows for all unresolved assignments.
 * 2. Admin opens the timetable builder and sees the Conflicts panel.
 * 3. Admin reviews suggested alternatives and picks one, or manually drags
 *    a slot on the grid (which may require moving another slot to make room).
 * 4. TimetableService::resolveConflict() is called:
 *    - Creates the timetable_slot row
 *    - Sets resolved_at, resolved_by, resolution_notes on this conflict row
 * 5. Conflict is now resolved (resolved_at is not null).
 *
 * Unresolved conflicts (resolved_at IS NULL) block timetable activation —
 * TimetableService::activate() checks for pending conflicts before activating.
 *
 * ── Kept for History ──────────────────────────────────────────────────────────
 * Conflict rows are never deleted (no soft deletes). Resolved conflicts remain
 * as an audit trail showing what was conflicted and how it was fixed. This is
 * useful for analyzing generator quality over time.
 *
 * ── Relationship to Other Tables ────────────────────────────────────────────
 *   timetable_conflicts  >─ timetables                      (belongs to a timetable)
 *   timetable_conflicts  >─ class_sections                  (which arm was being scheduled)
 *   timetable_conflicts  >─ teacher_class_section_subjects  (which assignment couldn't be placed)
 *   timetable_conflicts  >─ class_periods                   (which period caused the conflict, nullable)
 *   timetable_conflicts  >─ users (resolved_by)             (who resolved it)
 *
 * ── Belongs To ───────────────────────────────────────────────────────────────
 * Timetable Module → Generation layer. Written by TimetableGeneratorService.
 * Read and resolved by TimetableSlotController + ConflictPanel.vue.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_conflicts', function (Blueprint $table) {
            // ── Primary Key ───────────────────────────────────────────────────
            $table->id();
            // Auto-increment — conflicts are internal processing records,
            // not user-facing entities. No UUID needed.

            // ── Multi-Tenant Anchor ───────────────────────────────────────────
            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->cascadeOnDelete();

            // ── Parent Timetable ──────────────────────────────────────────────
            $table->foreignUuid('timetable_id')
                ->constrained('timetables')
                ->cascadeOnDelete();
            // Cascade — when a timetable is deleted, its conflicts go too.

            // ── What Couldn't Be Scheduled ────────────────────────────────────
            $table->foreignUuid('class_section_id')
                ->nullable()
                ->constrained('class_sections')
                ->nullOnDelete();
            // Which arm was being scheduled. Nullable to support school-level
            // conflicts (e.g. 'no_teacher_assigned' may not have a specific arm yet).

            $table->foreignId('teacher_class_section_subject_id')
                ->nullable()
                ->constrained('teacher_class_section_subjects')
                ->nullOnDelete();
            // Which teacher-subject assignment couldn't be placed.
            // Null for 'no_teacher_assigned' type (there is no assignment yet).

            $table->foreignId('class_period_id')
                ->nullable()
                ->constrained('class_periods')
                ->nullOnDelete();
            // The specific period that caused the conflict, when applicable.
            // For 'no_available_period' this is null (all periods failed).
            // For 'teacher_double_booked' this is the period where the teacher is busy.

            $table->unsignedTinyInteger('day_of_week')->nullable();
            // The day on which the conflict occurred, when applicable.
            // ISO 8601: 1=Monday...7=Sunday. Null for 'no_available_period'.

            // ── Conflict Classification ───────────────────────────────────────
            $table->string('conflict_type', 50);
            // Allowed values (application-enforced):
            //   'teacher_double_booked'
            //   'section_double_booked'
            //   'no_available_period'
            //   'no_teacher_assigned'
            //   'frequency_unmet'

            $table->string('description', 500)->nullable();
            // Human-readable explanation generated by the generator.
            // Example: "Mr. Abubakar is already teaching SSS 2B Chemistry
            // during Period 3 on Wednesday. Tried 12 other slots, all blocked."
            // Shown in the admin conflict panel for context.

            // ── Generator Suggestions ─────────────────────────────────────────
            $table->json('suggested_alternatives')->nullable();
            // Array of alternative slot options computed by the generator.
            // Structure:
            // [
            //   {
            //     "day_of_week": 2,
            //     "class_period_id": 5,
            //     "teacher_class_section_subject_id": 12,
            //     "score": 0.85,         // generator's confidence score
            //     "reason": "Tuesday Period 5 — teacher is free, section is free"
            //   },
            //   ...
            // ]
            // The admin picks one from the conflict panel, or ignores suggestions
            // and manually drags a slot into place on the grid.

            // ── Resolution ────────────────────────────────────────────────────
            $table->timestamp('resolved_at')->nullable();
            // Null = conflict is still pending. Not null = resolved.
            // TimetableService::activate() rejects if any unresolved conflict exists.

            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            // Which admin resolved the conflict.

            $table->string('resolution_notes', 500)->nullable();
            // Optional note from admin explaining their resolution choice.
            // Example: "Moved Physics to Friday to avoid Mr. Bello's JSS clash."

            // ── Timestamps ───────────────────────────────────────────────────
            $table->timestamps();
            // No soft deletes — conflicts are kept as an audit/analytics trail.
            // resolved_at serves the filtering purpose that soft deletes would.

            // ── Indexes ───────────────────────────────────────────────────────

            // Primary lookup: "all unresolved conflicts for timetable X"
            // Used by the admin conflict panel and the activation pre-check.
            $table->index(
                ['timetable_id', 'resolved_at'],
                'idx_timetable_conflicts_timetable_unresolved'
            );

            // School-scoped: "all pending conflicts for this school"
            // Used on the admin dashboard to show a conflict count badge.
            $table->index(
                ['school_id', 'resolved_at'],
                'idx_timetable_conflicts_school_unresolved'
            );

            // Filtering by type within a timetable.
            $table->index(
                ['timetable_id', 'conflict_type'],
                'idx_timetable_conflicts_type'
            );

            // Section-specific conflicts: "what couldn't be scheduled for SSS 1A?"
            $table->index(
                ['timetable_id', 'class_section_id'],
                'idx_timetable_conflicts_section'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_conflicts');
    }
};
