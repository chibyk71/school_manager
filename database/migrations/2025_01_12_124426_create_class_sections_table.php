<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_class_sections_table
 *
 * Creates the `class_sections` table — the "arms" or "streams" within a class level
 * (e.g., JSS 1A, JSS 1B, Primary 2 Diamond, Primary 2 Gold).
 *
 * ── What This Table Represents ───────────────────────────────────────────────
 * A ClassSection is the actual teaching group / classroom where students sit,
 * teachers teach, and attendance/results are recorded. It sits one level below
 * ClassLevel in the academic hierarchy:
 *
 *   School → SchoolSection → ClassLevel → ClassSection
 *   e.g.,   School → JSS   → JSS 1     → JSS 1A
 *
 * Sections are PERMANENT structures — they persist across academic sessions.
 * Students are enrolled into sections per session via the student_class_section_pivot.
 * An empty section is valid (e.g., before a new session starts).
 *
 * ── Naming Strategy ──────────────────────────────────────────────────────────
 * `name`         — The arm label only: "A", "B", "Diamond", "Gold", "1", "2"
 * `display_name` — Full human-readable label: "JSS 1A", "Primary 2 Diamond"
 *                  Stored (not computed at query time) for performance.
 *                  Auto-populated during bulk generate; admin can override.
 *                  NULL = not yet set (computed by model accessor as fallback).
 *
 * ── Key Design Decisions ─────────────────────────────────────────────────────
 * - `room` uniqueness is scoped to school_id (not globally) so two schools can
 *   both have a room called "Block A Room 1" without conflict.
 * - `form_teacher_id` references staff directly — nullable because a section
 *   may exist before a teacher is assigned. Set to null if teacher is deleted
 *   (nullOnDelete) to avoid orphan prevention blocking staff deletion.
 * - `capacity` is stored as unsignedSmallInteger (max 65,535) — more than enough
 *   for any classroom. Enforcement (hard block vs soft warning) is deferred to
 *   academic settings; this column stores the configured limit.
 * - `sort_order` uses 10-gap convention (10, 20, 30...) consistent with
 *   ClassLevel, Grade, DynamicEnum, SchoolSection in this codebase.
 * - `status` enum matches the pattern from SchoolSection and ClassLevel.
 * - Composite unique on (school_id, class_level_id, name) prevents duplicate
 *   arms within the same class level in the same school.
 *
 * ── Relationships ─────────────────────────────────────────────────────────────
 * - belongs to ClassLevel (the parent level, e.g., "JSS 1")
 * - belongs to School (multi-tenant anchor)
 * - belongs to Staff as form_teacher (nullable)
 * - has many Students via student_class_section_pivot (per academic session)
 * - has many TeacherClassSectionSubject assignments
 * - has many AttendanceSessions (future module)
 * - has many Timetable entries (future module)
 * - has many AssessmentResults (future module)
 *
 * ── Traits Used on the Model ─────────────────────────────────────────────────
 * - BelongsToSchool  (global scope: all queries auto-filtered to active school)
 * - SoftDeletes      (archive rather than destroy)
 * - HasTableQuery    (DataTable support with Purity filtering)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sections', function (Blueprint $table) {
            // ── Identity ──────────────────────────────────────────────────
            $table->uuid('id')->primary();

            // ── Multi-tenant anchor ────────────────────────────────────────
            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->restrictOnDelete(); // Prevent school deletion while sections exist

            // ── Parent class level ─────────────────────────────────────────
            $table->foreignUuid('class_level_id')
                ->constrained('class_levels')
                ->cascadeOnDelete(); // Deleting a class level removes its sections

            // ── Naming ────────────────────────────────────────────────────
            // Arm label only: "A", "B", "Diamond", "Gold"
            $table->string('name');

            // Full display label: "JSS 1A", "Primary 2 Diamond"
            // NULL = not yet populated; model accessor falls back to
            // classLevel.name + " " + name
            $table->string('display_name')->nullable();

            // ── Physical room reference ────────────────────────────────────
            // Optional physical room identifier (e.g., "Block A Room 3").
            // Unique per school — two sections in the same school cannot
            // share a room, but different schools may use identical room names.
            $table->string('room')->nullable();

            // ── Capacity ──────────────────────────────────────────────────
            // Maximum students allowed. 0 = uncapped / not configured.
            // Enforcement behaviour (hard block vs warning) is controlled by
            // academic settings — this column stores the configured limit only.
            $table->unsignedSmallInteger('capacity')->default(0);

            // ── Form teacher ──────────────────────────────────────────────
            // The staff member responsible for this section (class teacher /
            // form master). Nullable — a section may exist before assignment.
            // nullOnDelete: if the teacher's staff record is deleted, the
            // section still exists with no form teacher assigned.
            $table->foreignUuid('form_teacher_id')
                ->nullable()
                ->constrained('staff')
                ->nullOnDelete();

            // ── Display ordering ──────────────────────────────────────────
            // 10-gap convention (10, 20, 30...) — consistent with ClassLevel,
            // Grade, SchoolSection, DynamicEnum patterns in this codebase.
            // Lower value = displayed first within a class level.
            $table->unsignedSmallInteger('sort_order')->default(0);

            // ── Status ────────────────────────────────────────────────────
            $table->enum('status', ['active', 'inactive'])->default('active');

            // ── Audit ─────────────────────────────────────────────────────
            $table->softDeletes();
            $table->timestamps();

            // ── Indexes ───────────────────────────────────────────────────
            // Composite unique: one arm name per class level per school
            $table->unique(
                ['school_id', 'class_level_id', 'name'],
                'class_sections_school_level_name_unique'
            );

            // Room unique per school (not globally)
            $table->unique(
                ['school_id', 'room'],
                'class_sections_school_room_unique'
            );

            // Index for common query patterns
            $table->index(['class_level_id', 'status']);
            $table->index(['school_id', 'status']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sections');
    }
};
