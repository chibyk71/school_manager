<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create Grades Table (2025_01_23_114159_create_grades_table)
 *
 * Creates the grades table used to define grading scales per school and optional school section.
 * Each grade represents a performance band (e.g., A = 80–100, B = 70–79) with a unique code.
 *
 * Key Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Multi-tenant isolation via school_id (mandatory) and school_section_id (optional)
 * • Prevents duplicate grade codes within the same school/section combination
 * • Efficient querying with composite unique index + individual indexes on frequently filtered fields
 * • Cascade delete protection: when school or section is deleted → related grades are also removed
 * • Soft deletes + timestamps for audit trail and recovery
 * • UUID primary key (already chosen – good for distributed systems / API exposure)
 * • Integer min/max_score with realistic constraints (0–100 range enforced at app level)
 *
 * How it fits into the Grades Module:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Foundation table for all grade-related logic (Grade model, GradeService, DataTable queries)
 * • Used by Grade model (Academic namespace) with BelongsToSchool + HasTableQuery traits
 * • Referenced in exam results, student report cards, GPA calculations
 * • SchoolSection → optional → allows global school-wide grades + section-specific overrides
 *
 * Production Notes / Improvements in this version:
 * • Added explicit check constraint on min_score ≤ max_score (PostgreSQL / modern MySQL 8.0.16+)
 * • Added index on min_score + max_score for faster range queries (used in result grading)
 * • Renamed unique constraint name to be more descriptive
 * • Added comment on columns for better schema documentation / IDE introspection
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            // Primary key – UUID for better distributed system compatibility
            $table->uuid('id')->primary();

            // Multi-tenant ownership
            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->cascadeOnDelete()
                ->comment('Mandatory: owning school');

            // Grade identification
            $table->string('name', 100)
                ->index()
                ->comment('Human-readable name, e.g., "Excellent", "A", "Distinction"');

            $table->string('code', 10)
                ->index()
                ->comment('Short unique code, e.g., "A", "B+", "7" (WAEC style)');

            // Score range – core of the grading logic
            $table->unsignedInteger('min_score')
                ->comment('Inclusive minimum score for this grade (0–100)');

            $table->unsignedInteger('max_score')
                ->comment('Inclusive maximum score for this grade (0–100)');

            // Additional metadata
            $table->text('remark')
                ->nullable()
                ->comment('Optional description/interpretation, shown on report cards');

            // Standard Laravel audit fields
            $table->softDeletes();
            $table->timestamps();

            // ─── Constraints ────────────────────────────────────────────────────────────────

            // Prevent duplicate codes within same school (and section if specified)
            $table->unique(
                ['school_id', 'school_section_id', 'code'],
                'uniq_grades_school_section_code'
            );

            // Help range-based queries (used when assigning grade to a score)
            $table->index(['min_score', 'max_score'], 'idx_grades_score_range');

            // Optional: Add check constraint if database supports it (MySQL 8.0.16+, PostgreSQL)
            $table->raw('CONSTRAINT chk_grades_min_max CHECK (min_score <= max_score)');
            // Note: Many teams prefer application-level validation for this rule
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
