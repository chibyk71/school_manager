<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CreateSubjectsTable – v1.0
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT IT IMPLEMENTS
 * ─────────────────────────────────────────────────────────────────────────────
 * Creates the `subjects` table and three many-to-many pivot tables:
 *   • class_level_subject  — subjects taught at specific class levels
 *   • staff_subject        — teachers assigned to subjects
 *   • student_subject      — students enrolled in elective subjects
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FEATURES / PROBLEMS SOLVED
 * ─────────────────────────────────────────────────────────────────────────────
 * • UUID primary keys (consistent with codebase standard)
 * • school_id FK enforces multi-tenant isolation
 * • code column: unique per school (DB-level enforced via composite unique index)
 * • type + category stored as VARCHAR (enum-like, validated at app layer via Subject::types())
 * • is_active flag for soft enable/disable without deletion
 * • pass_mark and credit_hours for grading and scheduling
 * • color for timetable display (optional)
 * • sort for custom display ordering
 * • SoftDeletes: deleted_at column included
 * • All pivot tables use timestamps for audit trail
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * INDEXES
 * ─────────────────────────────────────────────────────────────────────────────
 * • subjects: composite unique on (school_id, code) — prevents duplicate codes per school
 * • subjects: index on (school_id, is_active, type, category) — for common filter combos
 * • subjects: index on school_id for tenant scoping queries
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Main subjects table ──────────────────────────────────────────
        Schema::create('subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();

            $table->string('name', 150);
            $table->string('code', 20);                    // e.g. MTH, ENG, PHY01
            $table->text('description')->nullable();

            // Type: core | elective | optional
            $table->string('type', 30)->default('core');

            // Category: sciences | arts | commerce | languages | technical | general
            $table->string('category', 30)->default('general');

            $table->boolean('is_active')->default(true);
            $table->string('color', 10)->nullable();       // Hex color for timetable
            $table->string('icon', 50)->nullable();

            $table->unsignedTinyInteger('pass_mark')->default(50);     // 0–100
            $table->unsignedTinyInteger('credit_hours')->nullable();   // Weekly hours

            $table->unsignedSmallInteger('sort')->default(0);

            $table->softDeletes();
            $table->timestamps();

            // Subject code must be unique within a school
            $table->unique(['school_id', 'code'], 'subjects_school_code_unique');

            // Composite index for common DataTable filter queries
            $table->index(['school_id', 'is_active', 'type', 'category'], 'subjects_filter_idx');
        });

        // ─── Pivot: subjects ↔ class_levels ──────────────────────────────
        Schema::create('class_level_subject', function (Blueprint $table) {
            $table->foreignUuid('class_level_id')->constrained('class_levels')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['class_level_id', 'subject_id']);
        });

        // ─── Pivot: subjects ↔ staff (teachers) ──────────────────────────
        Schema::create('staff_subject', function (Blueprint $table) {
            $table->foreignUuid('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['staff_id', 'subject_id']);
        });

        // ─── Pivot: subjects ↔ students (mainly for electives) ───────────
        Schema::create('student_subject', function (Blueprint $table) {
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['student_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subject');
        Schema::dropIfExists('staff_subject');
        Schema::dropIfExists('class_level_subject');
        Schema::dropIfExists('subjects');
    }
};
