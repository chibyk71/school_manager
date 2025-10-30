<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the lesson_plan_details table with school scoping and soft delete support.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lesson_plan_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignUuid('lesson_plan_id')->constrained('lesson_plans')->onDelete('cascade');
            $table->string('title');
            $table->string('sub_title')->nullable();
            $table->text('objective')->nullable();
            $table->json('activity');
            $table->json('teaching_method')->nullable();
            $table->json('evaluation')->nullable();
            $table->json('resources')->nullable();
            $table->integer('duration');
            $table->text('remarks')->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'published', 'rejected', 'archived'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the lesson_plan_details table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_plan_details');
    }
};
