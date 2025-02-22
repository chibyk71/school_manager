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
            $table->foreignUuid('assessment_id')->constrained('assessments')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subject')->constrained()->cascadeOnUpdate()->cascadeOnUpdate();
            $table->string('result');
            $table->string('remark')->nullable();
            $table->foreignId('class_section_id')->constrained('class_sections')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignUuid('grade_by')->references('id')->on('staff')->cascadeOnDelete()->cascadeOnUpdate();
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
