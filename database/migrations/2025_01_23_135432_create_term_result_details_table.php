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
        Schema::create('term_result_details', function (Blueprint $table) {
            $table->id();
            $table->uuid('school_id')->index()->nullable();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreignId('term_result_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->decimal('score', 5, 2);
            $table->text('class_teacher_remark')->nullable();
            $table->text('head_teacher_remark')->nullable();
            $table->timestamps();

            // Composite index for common queries
            $table->index(['school_id', 'term_result_id', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('term_result_details');
    }
};
