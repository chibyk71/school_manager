<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the syllabi table with school scoping, soft delete support, and approval workflow.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('syllabi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('class_level_id')->constrained('class_levels')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignUuid('term_id')->constrained('terms')->cascadeOnDelete();
            $table->string('topic');
            $table->string('sub_topic')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'published', 'rejected', 'archived'])->default('draft');
            $table->json('options')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
            $table->index('school_id');
            $table->index(['class_level_id', 'subject_id', 'term_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the syllabi table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('syllabi');
    }
};
