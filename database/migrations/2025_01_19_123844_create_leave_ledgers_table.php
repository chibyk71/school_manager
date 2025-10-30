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
        Schema::create('leave_ledgers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->unsignedInteger('encashed_days')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_id', 'user_id', 'leave_type_id', 'academic_session_id'], 'leave_ledgers_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_ledgers');
    }
};
