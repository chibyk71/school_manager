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
        Schema::create('term_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('school_id')->index()->nullable();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreignUuid('student_id')->references('id')->on('students')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('class_id')->constrained('class_levels')->cascadeOnDelete()->cascadeOnUpdate();
            $table->decimal('total_score', 5, 2);
            $table->decimal('average_score', 5, 2);
            $table->integer('position');
            $table->text('class_teacher_remark')->nullable();
            $table->text('head_teacher_remark')->nullable();
            $table->string('grade');
            $table->timestamps();

            // Composite index for common queries
            $table->index(['school_id', 'student_id', 'term_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('term_results');
    }
};
