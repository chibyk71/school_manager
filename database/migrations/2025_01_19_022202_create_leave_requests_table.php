<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('rejected_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_id', 'user_id', 'leave_type_id', 'start_date', 'end_date'], 'leave_requests_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
