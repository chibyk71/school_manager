<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: assessment_templates
 *
 * Stores the configurable scoring structure used by a school/section/class level.
 * This is the "blueprint" for how results are structured — it defines what components
 * exist (CA1, CA2, Exam, Project, etc.) and what weight each carries.
 *
 * Key Design Decisions:
 * - school_id scoped: each school defines its own templates (global defaults allowed via null)
 * - school_section_id nullable: can be school-wide OR section-specific (e.g. Primary vs SS)
 * - is_default: only one template can be default per school (enforced at app layer)
 * - components stored as JSON: [{name, label, max_score, weight_percent, is_exam}]
 *   This flexible JSON structure avoids the need for a separate components table while
 *   still being queryable via MySQL JSON functions.
 * - total_score: the sum of all component max_scores (usually 100, but configurable)
 * - is_active: soft toggle without deleting historical data
 *
 * Fits into the module:
 * - Used by Exam model to derive scoring structure
 * - Read by ScoreEntryController to render per-component score inputs
 * - Read by ResultComputationService to apply weights and compute totals
 * - Linked to Grade model via school_id for automatic grade assignment
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('school_section_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');                        // "Standard SS Template", "Primary Template"
            $table->string('description')->nullable();
            $table->boolean('is_default')->default(false); // Default template for this school/section
            $table->boolean('is_active')->default(true);

            /**
             * JSON structure for components:
             * [
             *   {
             *     "key": "ca1",
             *     "label": "1st CA",
             *     "max_score": 20,
             *     "weight_percent": 20,   // contribution to final score
             *     "is_exam": false,        // true = main exam component
             *     "sort_order": 1
             *   },
             *   { "key": "ca2", "label": "2nd CA", "max_score": 20, "weight_percent": 20, "is_exam": false, "sort_order": 2 },
             *   { "key": "exam", "label": "Exam", "max_score": 60, "weight_percent": 60, "is_exam": true, "sort_order": 3 }
             * ]
             */
            $table->json('components');

            /**
             * Total max score across all components (usually 100).
             * Stored denormalized for quick validation without JSON parsing.
             */
            $table->unsignedSmallInteger('total_score')->default(100);

            /**
             * Passing threshold for this template (e.g., 40 out of 100).
             * Used by ResultComputationService to flag failed subjects.
             */
            $table->unsignedSmallInteger('pass_mark')->default(40);

            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // A school should only have one default template per section
            $table->index(['school_id', 'school_section_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_templates');
    }
};
