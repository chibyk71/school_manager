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
        Schema::create('teacher_class_section_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('teacher_id')->constrained('staff')->cascadeOnDelete()->index();
            $table->foreignId('class_section_id')->constrained('class_sections')->cascadeOnDelete()->index();
            $table->uuid('subject_id')->index();
            $table->string('role')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['teacher_id', 'class_section_id', 'subject_id'], 'teacher_class_section_subject_unique');
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_class_section_subjects');
    }
};