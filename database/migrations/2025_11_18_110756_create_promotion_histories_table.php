<?php
// database/migrations/2025_11_18_110200_create_promotion_histories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permanent, immutable record of every promotion that has ever happened.
 *
 * Created only after successful execution of a batch.
 * Used for transcripts, certificates, and audit trails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')
                  ->constrained('students')
                  ->cascadeOnDelete();

            $table->foreignUuid('from_academic_session_id')
                  ->constrained('academic_sessions')
                  ->cascadeOnDelete();

            $table->foreignUuid('to_academic_session_id')
                  ->constrained('academic_sessions')
                  ->cascadeOnDelete();

            // From → To class tracking
            $table->foreignUuid('from_class_section_id')
                  ->nullable()
                  ->constrained('class_sections');
            $table->foreignUuid('to_class_section_id')
                  ->nullable()
                  ->constrained('class_sections');

            $table->enum('outcome', ['promoted', 'repeated', 'probation', 'graduated']);
            $table->text('remarks')->nullable();

            // Audit
            $table->foreignUuid('executed_by')->nullable()->constrained('users');
            $table->timestamp('executed_at');

            $table->timestamps();

            // Indexes for reporting
            $table->index('student_id');
            $table->index(['from_academic_session_id', 'to_academic_session_id'], 'academic_session_change');
            $table->index('outcome');
            $table->index('executed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_histories');
    }
};
