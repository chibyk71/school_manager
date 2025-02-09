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
            $table->id();
            $table->foreignId('class_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_class_section_subject_id')->constrained('teacher_class_section_subjects')->cascadeOnDelete();
            $table->foreignUuid('time_table_id')->constrained('time_tables')->cascadeOnDelete();
            $table->timestamps();
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
