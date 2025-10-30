<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('assessment_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->index()->nullable();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreignUuid('assessment_id')->constrained('assessments')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignUuid('grade_id')->nullable()->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('result');
            $table->string('remark')->nullable();
            $table->foreignUuid('class_section_id')->constrained('class_sections')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignUuid('graded_by')->references('id')->on('staff')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            // Composite index for common queries
            $table->index(['school_id', 'assessment_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_results');
    }
};
