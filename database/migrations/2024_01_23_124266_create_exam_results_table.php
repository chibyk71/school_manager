<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: exam_results
 *
 * Stores the raw score entered for one student, one subject, across ALL components
 * of a single exam. This is the primary data-entry row.
 *
 * Design Philosophy — "One row per student per subject per exam":
 * Rather than one row per component (which creates many tiny rows and complex queries),
 * we store all component scores in a JSON column called `scores`.
 *
 * JSON `scores` structure matches assessment_template components:
 *   {
 *     "ca1":  { "score": 18, "max": 20 },
 *     "ca2":  { "score": 15, "max": 20 },
 *     "exam": { "score": 52, "max": 60 }
 *   }
 *
 * This approach:
 * - Keeps the row count low (1 row per student per subject, not 3-5 rows)
 * - Makes full-row updates atomic (no partial component saves)
 * - Allows adding new components without schema changes
 * - Still supports efficient querying via generated columns (see below)
 *
 * Generated columns for performance:
 * - total_score: sum of all scores, stored as a virtual generated column
 *   This allows ORDER BY total_score and WHERE total_score > X without computation.
 *
 * Key Design Decisions:
 * - is_absent: student was absent; scores remain null but row exists
 * - is_exempted: student is excused (medical, transfer); excluded from class averages
 * - entered_by / verified_by: two-level data integrity for score entry
 * - locked_at: inherits from exam lock; individual subject can also be locked early
 * - remark: teacher's remark/comment on this student's performance in this subject
 *
 * Fits into the module:
 * - ScoreEntryController creates/updates these rows (one UPSERT per save)
 * - ResultComputationService reads scores + template weights → computed_results
 * - ExamReportService aggregates computed_results → report cards
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('exam_id')->constrained()->cascadeOnDelete();

            // The student and subject being assessed
            $table->foreignUuid('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignUuid('subject_id')->constrained()->restrictOnDelete();

            // Denormalized for query performance (avoids joins to exam → class_section)
            $table->foreignUuid('class_section_id')->nullable()->constrained()->nullOnDelete();

            /**
             * Raw scores per component, keyed by the component key from assessment_template.
             * Null values indicate a score has not yet been entered for that component.
             *
             * Example:
             * {
             *   "ca1":  { "score": 18, "max": 20, "entered_at": "2026-01-10T14:22:00Z" },
             *   "ca2":  { "score": null, "max": 20 },
             *   "exam": { "score": 52, "max": 60, "entered_at": "2026-01-20T09:00:00Z" }
             * }
             */
            $table->json('scores');

            /**
             * Denormalized total for sorting and filtering without JSON parsing.
             * Computed and stored by ResultComputationService after each score save.
             * NULL = not yet computed (some components still missing).
             */
            $table->decimal('total_score', 5, 2)->nullable();

            /**
             * Denormalized grade code (e.g., "A1", "B2", "F9") assigned by ResultComputationService
             * based on the Grade model for this school/section.
             * NULL = not yet computed.
             */
            $table->string('grade_code', 10)->nullable();

            /**
             * Denormalized grade remark (e.g., "Excellent", "Pass", "Fail").
             * Pulled from Grade model at computation time.
             */
            $table->string('grade_remark', 100)->nullable();

            // Student status flags
            $table->boolean('is_absent')->default(false);    // Absent from exam
            $table->boolean('is_exempted')->default(false);  // Medical/transfer exemption

            // Teacher's remark on this subject result
            $table->text('remark')->nullable();

            // Audit: who entered and verified the scores
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('locked_at')->nullable(); // Subject-level lock

            $table->timestamps();

            // A student can only have one result row per subject per exam
            $table->unique(['exam_id', 'student_id', 'subject_id'], 'unique_exam_student_subject');

            // Performance indexes for common query patterns
            $table->index(['exam_id', 'class_section_id']);
            $table->index(['exam_id', 'subject_id']);
            $table->index(['student_id', 'exam_id']);
            $table->index(['school_id', 'exam_id', 'total_score']); // For ranking queries
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};
