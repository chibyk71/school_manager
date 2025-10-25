<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the attendance_sessions table with school scoping and soft delete support.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('class_section_id')->constrained('class_sections')->cascadeOnDelete();
            $table->foreignId('class_period_id')->constrained('class_periods')->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('users')->onDelete('set null');
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('date_effective');
            $table->json('configs')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
            $table->index('school_id');
            $table->index('class_section_id');
            $table->index('class_period_id');
            $table->index('manager_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the attendance_sessions table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
