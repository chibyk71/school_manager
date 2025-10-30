<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the lesson_plans table with school scoping and soft delete support.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lesson_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignUuid('class_level_id')->constrained('class_levels')->onDelete('cascade');
            $table->foreignUuid('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignUuid('syllabus_detail_id')->nullable()->constrained('syllabus_details')->onDelete('set null');
            $table->string('topic');
            $table->date('date');
            $table->text('objective');
            $table->json('material')->nullable();
            $table->json('assessment')->nullable();
            $table->foreignUuid('staff_id')->constrained('staff')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
            $table->unique(['school_id', 'class_level_id', 'subject_id', 'topic', 'date'], 'unique_lesson_plan' );
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the lesson_plans table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_plans');
    }
};
