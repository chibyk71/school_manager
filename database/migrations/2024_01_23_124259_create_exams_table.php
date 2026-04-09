<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: exams
 *
 * Represents a specific examination event — e.g. "First Term 2025/2026 Examination".
 * An exam ties together:
 *   - An academic session + term (when it happens)
 *   - A class level or class section (who sits it)
 *   - An assessment template (how it is scored)
 *
 * Status machine:
 *   draft → published → ongoing → completed → results_approved
 *
 * Key Design Decisions:
 * - class_level_id nullable: exam can be for ALL sections of a level,
 *   or for a specific class_section_id if narrowed down
 * - class_section_id nullable: null means the exam applies to ALL sections
 *   of the class_level. When set, only that specific section is examined.
 * - assessment_template_id: links to the scoring structure. If template changes
 *   after results are entered, historical data is preserved.
 * - locked_at: once results are approved, the exam is locked to prevent further edits.
 *   This is the last write-protection gate.
 * - published_at: when the exam is made visible to teachers for score entry
 * - results_published_at: when students/parents can see results
 *
 * Fits into the module:
 * - ExamController creates/manages exam events
 * - ScoreEntryController reads exam to build score-entry form for each subject
 * - ResultComputationService uses exam to pull all enrolled students
 * - Exam is the anchor for all exam_results and computed_results rows
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->restrictOnDelete();

            // Academic context
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();

            // Class scope (level = all sections, section = specific arm)
            $table->foreignUuid('class_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('class_section_id')->nullable()->constrained()->nullOnDelete();

            // Scoring structure
            $table->foreignUuid('assessment_template_id')->constrained()->restrictOnDelete();

            $table->string('name');                         // "1st Term 2025/2026 Exam"
            $table->string('description')->nullable();

            /**
             * Status workflow:
             *   draft:            created but not visible to teachers
             *   published:        teachers can see and enter scores
             *   ongoing:          currently being conducted
             *   completed:        all scores entered, awaiting approval
             *   results_approved: results locked and published to students
             */
            $table->enum('status', [
                'draft',
                'published',
                'ongoing',
                'completed',
                'results_approved',
            ])->default('draft');

            // Key dates
            $table->date('exam_start_date')->nullable();
            $table->date('exam_end_date')->nullable();
            $table->dateTime('published_at')->nullable();        // When teachers can enter scores
            $table->dateTime('results_published_at')->nullable(); // When students can see results
            $table->dateTime('locked_at')->nullable();           // Final lock (no more edits)

            // Metadata
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Performance: finding all exams for a given term/session/class is very common
            $table->index(['school_id', 'academic_session_id', 'term_id']);
            $table->index(['school_id', 'class_level_id', 'class_section_id']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
