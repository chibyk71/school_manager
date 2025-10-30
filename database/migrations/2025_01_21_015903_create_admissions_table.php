<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the admissions table with school scoping, foreign keys, and soft delete support.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('class_level_id')->constrained('class_levels')->cascadeOnDelete();
            $table->foreignUuid('school_section_id')->constrained('school_sections')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->string('roll_no')->unique();
            $table->string('status')->default('pending'); // e.g., pending, approved, rejected
            $table->json('configs')->nullable(); // For additional settings
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
            $table->index('school_id');
            $table->index('student_id');
            $table->index('class_level_id');
            $table->index('school_section_id');
            $table->index('academic_session_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the admissions table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
