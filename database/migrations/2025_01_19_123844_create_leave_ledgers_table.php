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
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->index();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete()->index();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete()->index();
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