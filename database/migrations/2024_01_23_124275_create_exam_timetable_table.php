<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: exam_timetable
 *
 * Stores the scheduling of individual subject exams within an exam event.
 * Allows printing the timetable and tracking which subjects have been scheduled.
 *
 * Fits into the module:
 * - ExamTimetableController manages these rows
 * - Frontend renders a printable timetable view
 * - Collision detection (same class, same time) is enforced at the service layer
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_timetable', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('class_section_id')->nullable()->constrained()->nullOnDelete();

            $table->date('exam_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('venue', 150)->nullable();       // "Hall A", "JSS 1A Classroom"
            $table->string('invigilator', 150)->nullable(); // Free text or link to staff
            $table->text('notes')->nullable();

            $table->timestamps();

            // A subject can only be scheduled once per section per exam
            $table->unique(['exam_id', 'subject_id', 'class_section_id'], 'unique_exam_subject_section');
            $table->index(['exam_id', 'exam_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_timetable');
    }
};
