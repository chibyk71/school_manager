<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_teacher_class_section_subjects_table
 *
 * Creates the `teacher_class_section_subjects` table — the assignment record
 * linking a teacher (staff) to a subject they teach in a specific class section.
 *
 * ── What This Table Represents ───────────────────────────────────────────────
 * This table answers the question: "Which teacher teaches which subject in
 * which classroom?" It is the backbone of:
 *   - Timetable generation (who teaches what, where, when)
 *   - Result entry authorization (teacher can only enter results for their assigned subjects)
 *   - Attendance tracking (teacher can take attendance for their class)
 *   - Report generation (subject teacher on report cards)
 *
 * Example row:
 *   teacher_id = Mr. Adeyemi (staff)
 *   class_section_id = JSS 1A
 *   subject_id = Mathematics
 *   role = 'subject_teacher'
 *
 * ── The `role` Field ─────────────────────────────────────────────────────────
 * Allows multiple teachers per subject per section with different roles:
 *   'subject_teacher'  — primary teacher, full result entry rights
 *   'co_teacher'       — co-teaching arrangement
 *   'cover_teacher'    — temporary cover, limited rights
 *   'supervisor'       — oversight role (e.g., HOD observing)
 *
 * NULL role = unspecified (backward-compatible default).
 * Stored as string (not enum) to allow school-specific customization without
 * migration changes. Validation happens at the application layer.
 *
 * ── Session Scoping ──────────────────────────────────────────────────────────
 * This table does NOT have academic_session_id. Design rationale:
 * - Teacher-subject-section assignments are configured at the START of each
 *   session and remain stable throughout. They are managed (cleared and
 *   re-assigned) as part of the session setup workflow.
 * - Adding session_id here would require the timetable module to also carry it,
 *   creating a cascade of complexity. The timetable module owns session scoping.
 * - When a new session starts, the admin re-assigns teachers via a "Copy from
 *   last session" workflow (future feature) or manual re-assignment.
 * - SoftDeletes allows archiving old assignments without hard deletion,
 *   preserving historical data for result queries that reference old assignments.
 *
 * ── Unique Constraint ─────────────────────────────────────────────────────────
 * (teacher_id, class_section_id, subject_id) is unique.
 * This means one teacher can only be assigned to teach a subject in a section once.
 * Different teachers CAN teach the same subject in the same section (different roles).
 * The same teacher CAN teach the same subject in different sections.
 *
 * ── Relationships ─────────────────────────────────────────────────────────────
 * - belongs to Staff (as teacher)
 * - belongs to ClassSection
 * - belongs to Subject
 * - belongs to School (for multi-tenant scoping and direct filtering)
 *
 * ── ClassSection Module Scope ─────────────────────────────────────────────────
 * This table is managed from the ClassSection module's "Subject Assignments" tab:
 *   - View all subject-teacher assignments for a section
 *   - Assign a teacher to a subject in this section
 *   - Remove/change assignments
 *
 * The Staff/HR module reads this table to show "what does this teacher teach?"
 * The Timetable module reads this to know valid teacher-subject pairings.
 * The Results module reads this for authorization checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_class_section_subjects', function (Blueprint $table) {
            // ── Primary key ───────────────────────────────────────────────
            $table->id();

            // ── Multi-tenant anchor ────────────────────────────────────────
            // Denormalized for direct school-scoped filtering without
            // needing to join through class_sections every time.
            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->cascadeOnDelete();

            // ── Core foreign keys ─────────────────────────────────────────
            $table->foreignUuid('teacher_id')
                ->constrained('staff')
                ->cascadeOnDelete(); // Remove assignments when staff record is deleted

            $table->foreignUuid('class_section_id')
                ->constrained('class_sections')
                ->cascadeOnDelete(); // Remove assignments when section is permanently deleted

            $table->foreignUuid('subject_id')
                ->constrained('subjects')
                ->cascadeOnDelete(); // Remove assignments when subject is deleted

            // ── Role ──────────────────────────────────────────────────────
            // The teacher's role for this specific assignment.
            // Common values: 'subject_teacher', 'co_teacher', 'cover_teacher', 'supervisor'
            // NULL = no specific role designated (acts as subject_teacher by default).
            // Stored as string — not enum — to allow customization without migrations.
            $table->string('role')->nullable()->index();

            // ── Audit ─────────────────────────────────────────────────────
            // SoftDeletes preserves historical assignment records needed by
            // the Results and Timetable modules to reference past assignments.
            $table->softDeletes();
            $table->timestamps();

            // ── Constraints ───────────────────────────────────────────────
            // One assignment per teacher per subject per section.
            // Multiple teachers CAN teach the same subject in the same section
            // (they just get separate rows with different roles).
            $table->unique(
                ['teacher_id', 'class_section_id', 'subject_id'],
                'teacher_section_subject_unique'
            );

            // ── Indexes for common query patterns ─────────────────────────
            // "What subjects does this teacher teach?" (teacher portal)
            $table->index(
                ['teacher_id', 'school_id'],
                'teacher_school_idx'
            );

            // "Who teaches what in this section?" (section detail page)
            $table->index(
                ['class_section_id', 'subject_id'],
                'section_subject_idx'
            );

            // "Which sections teach this subject?" (subject management)
            $table->index(
                ['subject_id', 'school_id'],
                'subject_school_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_class_section_subjects');
    }
};
