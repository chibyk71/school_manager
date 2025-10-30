<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the syllabus_details table with school scoping, soft delete support, and approval workflow.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('syllabus_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('syllabus_id')->constrained('syllabi')->cascadeOnDelete();
            $table->unsignedInteger('week');
            $table->text('objectives')->nullable();
            $table->string('topic');
            $table->json('sub_topics')->nullable();
            $table->text('description')->nullable();
            $table->json('resources')->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'published', 'rejected', 'archived'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the syllabus_details table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('syllabus_details');
    }
};
