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
        Schema::create('assessment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete()->cascadeOnUpdate();
            $table->uuid('student_id')->index();
            $table->foreign('student_id')->references('id')->on('student')->cascadeOnDelete();
            $table->foreignId('subject')->constrained()->cascadeOnUpdate()->cascadeOnUpdate();
            $table->string('result');
            $table->string('remark')->nullable();
            $table->foreignId('class_section_id')->constrained('class_sections')->cascadeOnDelete()->cascadeOnUpdate();
            $table->uuid('graded_by')->index();
            $table->foreign('grade_by')->references('id')->on('staff')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_results');
    }
};
