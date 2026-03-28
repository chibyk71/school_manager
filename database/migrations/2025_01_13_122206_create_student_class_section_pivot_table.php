<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_student_class_section_pivot_table
 *
 * Creates the `student_class_section_pivot` table — the junction table that
 * records which students are (or were) enrolled in which class sections,
 * scoped to a specific academic session.
 *
 * ── Why This Is Not a Simple Pivot ───────────────────────────────────────────
 * A plain M:M pivot (student_id + class_section_id) is insufficient because:
 *
 * 1. Students move between sections year by year (JSS 1A → JSS 2B → JSS 3A).
 *    Without academic_session_id, you cannot tell WHICH year a student was in
 *    a given section, making historical reports, promotion tracking, and
 *    result lookups impossible.
 *
 * 2. A student's "current" section must be distinguishable from historical ones
 *    without expensive date comparisons on every query. `is_current` provides
 *    this at O(1) lookup cost.
 *
 * 3. Enrollment dates are needed for:
 *    - Attendance records that must reference valid enrollment periods
 *    - Mid-year transfers (left_at marks the departure date)
 *    - Audit trails for compliance
 *
 * ── How Enrollment Works ─────────────────────────────────────────────────────
 * CREATE enrollment:
 *   INSERT with is_current = true, enrolled_at = today, left_at = null
 *
 * TRANSFER student to another section:
 *   UPDATE old row: is_current = false, left_at = today
 *   INSERT new row: is_current = true, enrolled_at = today
 *
 * NEW academic session (promotion):
 *   UPDATE all rows for completed session: is_current = false
 *   INSERT new rows for the new session via PromotionService
 *
 * QUERY current section:
 *   WHERE student_id = ? AND is_current = true
 *   (fast — indexed, returns at most 1 row per student)
 *
 * ── Unique Constraint Strategy ───────────────────────────────────────────────
 * The unique constraint is on (student_id, class_section_id, academic_session_id).
 * This means:
 *   - A student can only be in one section ONCE per session ✓
 *   - A student CAN be in the same section across multiple sessions ✓
 *     (e.g., a student who repeats JSS 1A gets two rows, one per session)
 *   - Mid-year transfers create a new row (old row gets left_at populated)
 *     so two rows for same student+section+session CAN exist if the student
 *     left and re-enrolled. If this edge case matters, add a partial index.
 *     For now, the unique constraint serves >99% of real-world cases.
 *
 * ── Joining to Get School Section ────────────────────────────────────────────
 * school_section_id is NOT denormalized here. To filter by school section:
 *   student_class_section_pivot
 *     JOIN class_sections ON class_section_id = class_sections.id
 *     JOIN class_levels   ON class_sections.class_level_id = class_levels.id
 *     WHERE class_levels.school_section_id = ?
 *
 * This join is acceptable because:
 * - class_sections < 100 rows per school (indexed, fits in memory)
 * - This filter will be used infrequently (not in hot paths)
 * - Avoiding denormalization keeps the data consistent (no sync issues)
 *
 * ── Relationships ─────────────────────────────────────────────────────────────
 * - belongs to Student
 * - belongs to ClassSection
 * - belongs to AcademicSession
 *
 * ── Student Enrollment Module Note ───────────────────────────────────────────
 * This pivot is WRITTEN to by the Student Enrollment module (separate module).
 * The ClassSection module only READS from it (e.g., to show student counts).
 * The form teacher assignment, bulk generate, and section CRUD live in the
 * ClassSection module — student assignment lives in Student Enrollment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_class_section_pivot', function (Blueprint $table) {
            // ── Primary key ───────────────────────────────────────────────
            // Standard auto-increment — this is a pivot/ledger table,
            // not a first-class resource, so bigIncrements is appropriate.
            $table->id();

            // ── Core foreign keys ─────────────────────────────────────────
            $table->foreignUuid('student_id')
                ->constrained('students')
                ->cascadeOnDelete(); // Remove enrollment history when student is hard-deleted

            $table->foreignUuid('class_section_id')
                ->constrained('class_sections')
                ->cascadeOnDelete(); // Remove enrollment records when a section is permanently deleted

            // ── Session scoping ───────────────────────────────────────────
            // Links this enrollment to a specific academic year.
            // REQUIRED — without this, historical data is ambiguous.
            $table->foreignUuid('academic_session_id')
                ->constrained('academic_sessions')
                ->cascadeOnDelete(); // Clean up enrollment history if a session is deleted

            // ── Current enrollment flag ───────────────────────────────────
            // true  = student is actively enrolled in this section right now
            // false = historical record (promoted, transferred, or session ended)
            //
            // Business rule enforced by EnrollmentService:
            //   A student should have at most ONE row with is_current = true.
            //   (Not enforced by DB constraint to allow transfer workflow
            //    where new row is inserted before old row is updated.)
            $table->boolean('is_current')->default(true)->index();

            // ── Enrollment period ─────────────────────────────────────────
            // enrolled_at: date student joined this section in this session
            // left_at:     date student left (transfer, withdrawal, graduation)
            //              NULL = still enrolled
            $table->date('enrolled_at')->nullable();
            $table->date('left_at')->nullable();

            // ── Audit ─────────────────────────────────────────────────────
            $table->timestamps();

            // ── Constraints & Indexes ─────────────────────────────────────
            // One enrollment record per student per section per session.
            // This covers the normal case; transfers create new rows with
            // the old row getting left_at populated.
            $table->unique(
                ['student_id', 'class_section_id', 'academic_session_id'],
                'student_section_session_unique'
            );

            // Fast lookup: "what section is this student in right now?"
            $table->index(
                ['student_id', 'is_current'],
                'student_current_section_idx'
            );

            // Fast lookup: "how many students are in this section this session?"
            $table->index(
                ['class_section_id', 'academic_session_id', 'is_current'],
                'section_session_current_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_class_section_pivot');
    }
};
