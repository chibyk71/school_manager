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
        Schema::create('attendance_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foriegnId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->uuid('attendable_id')->index();
            $table->string('attendable_type');
            $table->enum('status',['present','absent','late','leave','holiday'])->default('absent');
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_ledgers');
    }
};
