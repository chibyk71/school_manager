<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('assessment_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('school_id')->index()->nullable();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreignUuid('assessment_id')->constrained('assessments')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('class_section_id')->constrained('class_sections')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignUuid('invigilator_id')->constrained('staff')->cascadeOnDelete()->cascadeOnUpdate();
            $table->date('start_date')->index();
            $table->date('end_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['draft', 'active', 'completed'])->default('draft');
            $table->string('venue')->nullable();
            $table->timestamps();

            // Composite index for common queries
            $table->index(['school_id', 'assessment_id', 'start_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_schedules');
    }
};
