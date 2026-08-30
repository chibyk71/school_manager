<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the admissions table — school offer of a place to a candidate.
 * application_id and offer lifecycle columns added in evolve migration
 * (student_applications is created later in the migration timeline).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->restrictOnDelete();

            $table->foreignUuid('student_id')
                ->nullable()
                ->constrained('students')
                ->nullOnDelete();

            $table->foreignUuid('class_level_id')
                ->constrained('class_levels')
                ->restrictOnDelete();

            $table->foreignUuid('school_section_id')
                ->nullable()
                ->constrained('school_sections')
                ->nullOnDelete();

            $table->foreignUuid('academic_session_id')
                ->constrained('academic_sessions')
                ->restrictOnDelete();

            $table->string('roll_no')->nullable();
            $table->string('status')->default('offered');
            $table->json('configs')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at');
            $table->index('school_id');
            $table->index('student_id');
            $table->index('class_level_id');
            $table->index('school_section_id');
            $table->index('academic_session_id');
            $table->index(['school_id', 'status'], 'idx_admissions_school_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
