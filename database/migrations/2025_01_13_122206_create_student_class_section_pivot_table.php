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
        Schema::create('student_class_section_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('class_section_id')->constrained('class_sections')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'class_section_id'], 'student_class_section_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_class_section_pivot');
    }
};
