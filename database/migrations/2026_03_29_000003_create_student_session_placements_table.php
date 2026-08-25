<?php

/**
 * Migration: 2026_03_30_000003_create_student_session_placements_table.php
 *
 * Creates the student_session_placements table — the session-aware academic placement record for students.
 *
 * ── Core Design Principle ────────────────────────────────────────────────────
 *
 * The students table answers "Who is a student at this school and what is their overall status?"
 * This table answers "Where exactly is the student placed academically in a given session?"
 *
 * This separation enables:
 * 1. Full academic history — one Student record, many placement records (one per session).
 * 2. Clean promotion/transfer workflow — new placement created for next session; old one preserved.
 * 3. Mid-session changes (e.g., arm/class move) — update existing placement rather than creating duplicates.
 * 4. High-performance queries for class lists, attendance, fees, etc.
 *
 * Relationship Flow:
 *   Profile → Student (school-scoped) → StudentSessionPlacement (session-scoped)
 *
 * No school_id column is present — school context is inherited through the Student record
 * (via BelongsToSchool trait on Student model). This avoids unnecessary denormalization.
 *
 * ── Key Constraints ──────────────────────────────────────────────────────────
 *
 * - Unique index on (student_id, academic_session_id) → one placement per student per session.
 * - is_current flag is denormalized for fast "current placement" lookups.
 *   StudentPlacementService enforces that only one placement per student can be is_current = true.
 *
 * ── Column Purpose ───────────────────────────────────────────────────────────
 *
 * - class_level_id     → Required (e.g., JSS 1, Primary 3, SSS 2)
 * - class_section_id   → Nullable (arm can be assigned later, e.g., JSS 1A)
 * - enrolled_at        → When the student started this placement
 * - left_at            → When the student left this placement (withdrawn, transferred, graduated, etc.)
 * - is_current         → Denormalized flag for current active placement
 * - notes              → Placement-specific admin notes
 *
 * ── Status & Outcome Fields ──────────────────────────────────────────────────
 *
 * promotion_outcome is stored as a plain string (NOT a database ENUM).
 * This allows schools to define custom promotion outcomes via HasDynamicEnum trait
 * on the StudentSessionPlacement model if needed in the future.
 *
 * Fits into the Student Management Module:
 * - Heavily used by StudentPlacementService, StudentEnrollmentService, and PromotionService (future).
 * - Accessed via Student::currentPlacement(), Student::sessionPlacements(), etc.
 * - Powers class lists, attendance marking, report card generation, and promotion workflows.
 * - Integrates with HasTableQuery for advanced placement-based DataTables.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_session_placements', function (Blueprint $table) {

            // ── Primary Key ───────────────────────────────────────────────────
            // Auto-increment integer is acceptable here as this is a high-volume
            // join table and UUID overhead is unnecessary for internal linking.
            $table->id();

            // ── Core Relationships ────────────────────────────────────────────
            $table->foreignUuid('student_id')
                ->constrained('students')
                ->cascadeOnDelete(); // Delete placement history when student is hard-deleted

            $table->foreignUuid('academic_session_id')
                ->constrained('academic_sessions')
                ->restrictOnDelete();

            // ── Academic Placement Details ────────────────────────────────────
            $table->foreignUuid('class_level_id')
                ->constrained('class_levels')
                ->restrictOnDelete();

            $table->foreignUuid('class_section_id')
                ->nullable()
                ->constrained('class_sections')
                ->nullOnDelete(); // If section is deleted, placement loses arm but remains

            // ── Timing ────────────────────────────────────────────────────────
            $table->date('enrolled_at');
            $table->date('left_at')->nullable();   // Set when student leaves this placement

            // ── Current Placement Flag ────────────────────────────────────────
            // Denormalized for performance. StudentPlacementService ensures only one true per student.
            $table->boolean('is_current')->default(false);

            // ── Promotion / Placement Metadata ────────────────────────────────
            // Stored as string to support HasDynamicEnum in the future.
            $table->string('promotion_outcome', 50)->nullable();

            // Optional link to a promotion batch/job (for bulk promotion tracking)
            $table->unsignedBigInteger('promotion_batch_id')->nullable();

            // ── Notes ─────────────────────────────────────────────────────────
            $table->text('notes')->nullable();

            // ── Timestamps ────────────────────────────────────────────────────
            $table->timestamps();

            // ── Constraints & Indexes ─────────────────────────────────────────
            // Enforce one placement per student per academic session
            $table->unique(
                ['student_id', 'academic_session_id'],
                'uq_student_session_placement'
            );

            // Fast lookup for current placement
            $table->index('is_current', 'idx_placement_is_current');

            // Common queries: students in a specific section this session
            $table->index(
                ['class_section_id', 'academic_session_id', 'is_current'],
                'idx_placement_section_session_current'
            );

            // Common queries: students at a class level this session
            $table->index(
                ['class_level_id', 'academic_session_id', 'is_current'],
                'idx_placement_level_session_current'
            );

            // For promotion history and reporting
            $table->index(
                ['student_id', 'academic_session_id'],
                'idx_placement_student_session'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_session_placements');
    }
};
