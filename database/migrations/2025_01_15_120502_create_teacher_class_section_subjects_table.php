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
            $table->string('teacher_id')->index();
            $table->foreign('teacher_id')->references('id')->on('staff')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('class_section_subject_id')->constrained('class_section_subjects')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();
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
