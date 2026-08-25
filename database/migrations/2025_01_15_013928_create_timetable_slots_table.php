<?php

/**
 * Migration: create_timetable_slots_table
 *
 * Creates the `timetable_slots` table — the heart of the timetable module.
 * Each row is one lesson assignment: "On Wednesday, Period 3, Class SSS 1A
 * has Biology taught by Mr. Abubakar."
 *
 * ── What This Table Stores ───────────────────────────────────────────────────
 * A TimetableSlot answers five questions:
 *   - WHICH timetable?              → timetable_id
 *   - WHICH class section (arm)?    → class_section_id
 *   - WHICH period of the day?      → class_period_id
 *   - WHICH day of the week?        → day_of_week
 *   - WHO teaches WHAT subject?     → teacher_class_section_subject_id
 *     (this single FK encodes teacher + subject + class section together)
 *
 * ── How Conflicts Are Prevented ───────────────────────────────────────────────
 * Two types of conflicts are prevented:
 *
 * 1. SECTION DOUBLE-BOOKING (DB-enforced):
 *    A class section cannot have two subjects at the same period on the same day.
 *    Unique constraint: (timetable_id, class_section_id, class_period_id, day_of_week)
 *
 * 2. TEACHER DOUBLE-BOOKING (application-enforced):
 *    A teacher cannot teach two different classes at the same period on the same day.
 *    This CANNOT be expressed as a DB unique constraint because the teacher_id is
 *    embedded in teacher_class_section_subject_id — two TCSS rows with the same
 *    teacher_id but different class_section_id are separate rows.
 *    Enforced by: NoTeacherConflict validation rule + TimetableService.
 *
 * ── The `teacher_class_section_subject_id` FK Explained ──────────────────────
 * This FK points to `teacher_class_section_subjects` which already encodes:
 *   - teacher_id (who is teaching)
 *   - class_section_id (which arm)
 *   - subject_id (what subject)
 *   - role (subject_teacher, co_teacher, etc.)
 *
 * Using this FK instead of three separate FKs (teacher_id, class_section_id,
 * subject_id) ensures the slot always refers to a valid, pre-approved assignment.
 * You cannot slot a teacher for a subject they're not assigned to in this arm.
 *
 * ── Why class_section_id Is Stored Separately ────────────────────────────────
 * The class_section_id is derivable from teacher_class_section_subject_id.
 * It is stored separately because:
 *   1. It enables the DB unique constraint for section double-booking.
 *   2. It avoids a join when rendering the timetable grid filtered by class section.
 *   3. The timetable view (student/teacher perspective) always filters by class_section_id.
 *
 * The application ensures class_section_id always matches the class_section_id
 * of the referenced teacher_class_section_subject row. The StoreTimetableSlotRequest
 * validates this.
 *
 * ── `is_manually_placed` Flag ────────────────────────────────────────────────
 * When an admin drags a slot to a new position (or places it manually), this flag
 * is set to true. The generator respects this flag and will NOT move or overwrite
 * manually-placed slots on re-generation. Only auto-generated slots (flag=false)
 * are cleared and re-generated when admin triggers re-generation.
 *
 * This allows partial manual overrides: admin auto-generates 90% of the timetable,
 * then manually fixes the 10% that conflicts, then re-generates without losing the fixes.
 *
 * ── Relationship to Other Tables ────────────────────────────────────────────
 *   timetable_slots  >─ timetables                      (belongs to a timetable)
 *   timetable_slots  >─ class_sections                  (which arm)
 *   timetable_slots  >─ class_periods                   (which period of the day)
 *   timetable_slots  >─ teacher_class_section_subjects  (teacher + subject assignment)
 *
 * ── Belongs To ───────────────────────────────────────────────────────────────
 * Timetable Module → Core data layer. The primary output of the timetable module.
 * Written by: TimetableGeneratorService (auto) and TimetableSlotController (manual).
 * Read by: Timetable grid views, teacher schedule views, student schedule views.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_slots', function (Blueprint $table) {
            // ── Primary Key ───────────────────────────────────────────────────
            $table->uuid('id')->primary();
            // UUID — slots are user-facing (referenced in conflict resolution UI,
            // drag-and-drop operations, and potentially shared via deep links).

            // ── Multi-Tenant Anchor (denormalized) ────────────────────────────
            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->cascadeOnDelete();
            // Denormalized for query performance. The timetable grid view for a
            // school always scopes by school_id first.

            // ── Parent Timetable ──────────────────────────────────────────────
            $table->foreignUuid('timetable_id')
                ->constrained('timetables')
                ->cascadeOnDelete();
            // Deleting a timetable removes all its slots. Cascade is correct —
            // slots have no existence independent of their timetable.

            // ── Class Section (Arm) ───────────────────────────────────────────
            $table->foreignUuid('class_section_id')
                ->constrained('class_sections')
                ->restrictOnDelete();
            // Restrict — cannot delete a class section that has timetable slots.
            // Denormalized from teacher_class_section_subject for:
            //   (a) DB-level section uniqueness constraint
            //   (b) efficient grid queries filtered by class section

            // ── Period ───────────────────────────────────────────────────────
            $table->foreignId('class_period_id')
                ->constrained('class_periods')
                ->restrictOnDelete();
            // Restrict — cannot delete a period that is used in active slots.
            // The period defines WHEN in the day this lesson occurs.
            // Start time is computed: sum of preceding period durations + schedule start.

            // ── Teacher + Subject Assignment ─────────────────────────────────
            $table->foreignId('teacher_class_section_subject_id')
                ->constrained('teacher_class_section_subjects')
                ->restrictOnDelete();
            // Restrict — cannot delete a teacher assignment that is used in slots.
            // This FK encodes WHO teaches WHAT in WHICH arm.
            // Using this FK (instead of separate teacher_id + subject_id) ensures
            // only valid, pre-approved assignments can be slotted.

            // ── Day of Week ───────────────────────────────────────────────────
            $table->unsignedTinyInteger('day_of_week');
            // ISO 8601: 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday,
            //           5=Friday, 6=Saturday, 7=Sunday
            // Must match a day that has a corresponding timetable_day_schedules row.
            // Validated by StoreTimetableSlotRequest.

            // ── Manual Override Flag ─────────────────────────────────────────
            $table->boolean('is_manually_placed')->default(false);
            // false = placed by auto-generator (can be overwritten on re-generation)
            // true  = placed/moved by admin (generator will not touch this slot)
            // Set to true automatically when admin drags a slot or uses manual assignment.

            // ── Optional Notes ────────────────────────────────────────────────
            $table->string('notes', 255)->nullable();
            // Slot-level notes for admin use. Examples:
            //   "Lab session — use Science block B"
            //   "Double period — continues from Period 4"

            // ── Timestamps & Soft Deletes ─────────────────────────────────────
            $table->timestamps();
            $table->softDeletes();
            // Soft delete — slots may be referenced by attendance records or
            // substitution logs. Hard deletion must be prevented for data integrity.

            // ── DB-Level Constraints ─────────────────────────────────────────

            // Section uniqueness: a class section cannot have two subjects at the
            // same period on the same day within a timetable.
            $table->unique(
                ['timetable_id', 'class_section_id', 'class_period_id', 'day_of_week'],
                'uq_timetable_slots_section_period_day'
            );

            // ── Indexes ───────────────────────────────────────────────────────

            // Primary grid query: "give me all slots for timetable X"
            // Covers the full grid render for the timetable builder.
            $table->index(
                ['timetable_id', 'day_of_week'],
                'idx_timetable_slots_timetable_day'
            );

            // Class section view: "give me all slots for SSS 1A in this timetable"
            // Used by the student/teacher timetable view.
            $table->index(
                ['timetable_id', 'class_section_id'],
                'idx_timetable_slots_section'
            );

            // Teacher schedule view: all slots for a teacher across all timetables.
            // Used by "My Schedule" view for teachers.
            $table->index(
                ['teacher_class_section_subject_id'],
                'idx_timetable_slots_tcss'
            );

            // Conflict detection query: "does this teacher already have a slot at
            // this period on this day in this timetable?"
            // Used by NoTeacherConflict validation rule.
            $table->index(
                ['timetable_id', 'class_period_id', 'day_of_week'],
                'idx_timetable_slots_period_day'
            );

            // School-scoped queries for dashboard/reporting.
            $table->index(
                ['school_id', 'timetable_id'],
                'idx_timetable_slots_school_timetable'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
    }
};
