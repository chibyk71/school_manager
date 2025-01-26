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
        Schema::create('lesson_plan_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_plan_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('sub_title')->nullable();
            $table->text('objective')->nullable();
            $table->json('activity');
            $table->json('teaching_method')->nullable();
            $table->json('evaluation')->nullable();
            $table->json('resources')->nullable(); // This should be an array of resources used in the lesson plan eg ['book', 'whiteboard', 'chalk']
            $table->integer('duration'); // This should be in minutes
            $table->text('remarks')->nullable();
            $table->enum('status', ['published', 'draft', 'archived'])->default('draft'); // This should be an enum of 'draft', 'published', 'archived'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_plan_details');
    }
};
