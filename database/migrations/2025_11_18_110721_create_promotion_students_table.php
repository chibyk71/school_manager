<?php
// database/migrations/2025_11_18_110100_create_promotion_students_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temporary holding table for students in a promotion batch.
 *
 * This is where the system calculates recommendation (promote/repeat/probation)
 * and the principal can override before final execution.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('promotion_batch_id')
                  ->constrained('promotion_batches')
                  ->cascadeOnDelete();

            $table->foreignUuid('student_id')
                  ->constrained('students')
                  ->cascadeOnDelete();

            // Current assignment (source)
            $table->foreignUuid('current_class_section_id')
                  ->nullable()
                  ->constrained('class_sections');

            // Target assignment (destination)
            $table->foreignUuid('next_class_section_id')
                  ->nullable()
                  ->constrained('class_sections');

            // Calculated recommendation
            $table->enum('recommendation', ['promote', 'repeat', 'probation', 'graduated'])
                  ->default('promote');

            // Principal override
            $table->enum('final_decision', ['promote', 'repeat', 'probation', 'graduated'])
                  ->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignUuid('overridden_by')->nullable()->constrained('users');

            // Metadata
            $table->unsignedInteger('failed_subjects_count')->default(0);
            $table->decimal('average_score', 5, 2)->nullable();
            $table->boolean('is_processed')->default(false);
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            // Unique: one student per batch
            $table->unique(['promotion_batch_id', 'student_id'], 'batch_student_unique');

            // Indexes
            $table->index('recommendation');
            $table->index('final_decision');
            $table->index('is_processed');
            $table->index(['promotion_batch_id', 'recommendation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_students');
    }
};
