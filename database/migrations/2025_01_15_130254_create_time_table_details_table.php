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
        Schema::create('time_table_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('timetable_id')->constrained('time_tables')->cascadeOnDelete();
            $table->foreignUuid('class_period_id')->constrained('class_periods')->cascadeOnDelete();
            $table->foreignId('teacher_class_section_subject_id')->constrained('teacher_class_section_subjects')->cascadeOnDelete();
            $table->enum('day', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
            $table->string('start_time');
            $table->string('end_time');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['timetable_id', 'class_period_id', 'day', 'start_time'], 'time_table_details_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_table_details');
    }
};
