<?php
// database/migrations/2025_11_18_110000_create_promotion_batches_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotion Batch represents one end-of-session promotion cycle.
 *
 * Example: "2025/2026 Session Promotion" → handles all students moving from
 * JSS1 → JSS2, SSS1 → SSS2, etc.
 *
 * Status flow:
 * - pending     → batch created, awaiting principal review
 * - reviewing   → principal is making changes
 * - approved    → principal approved → ready to execute
 * - rejected    → principal rejected with reason
 * - executing   → queue running
 * - completed   → all students promoted successfully
 * - failed      → job batch failed
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_session_id')
                  ->constrained('academic_sessions')
                  ->cascadeOnDelete();
            $table->foreignUuid('school_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('name');                    // e.g., "2025/2026 Promotion"
            $table->text('description')->nullable();

            $table->enum('status', [
                'pending', 'reviewing', 'approved',
                'rejected', 'executing', 'completed', 'failed'
            ])->default('pending');

            // Principal approval fields
            $table->foreignUuid('principal_id')->nullable()->constrained('users');
            $table->timestamp('principal_reviewed_at')->nullable();
            $table->text('principal_comments')->nullable();

            // Execution tracking
            $table->timestamp('executed_at')->nullable();
            $table->unsignedBigInteger('total_students')->default(0);
            $table->unsignedBigInteger('processed_students')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['school_id', 'academic_session_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_batches');
    }
};
